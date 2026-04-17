<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Support\ParentPhone;
use App\Services\ParentPaymentNotificationService;
use App\Services\ToyyibPayService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ParentPaymentController extends Controller
{
    public function __construct(
        private readonly ToyyibPayService $toyyibPayService,
        private readonly ParentPaymentNotificationService $paymentNotificationService
    ) {
    }

    public function checkout(Request $request, FamilyBilling $familyBilling): View
    {
        $this->authorizeParentFamilyBilling($request, $familyBilling);

        $children = Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        $isTesterMode = (bool) $request->user()?->isParentTester();
        $checkoutOutstanding = (float) $familyBilling->outstanding_amount;
        $checkoutBaseAmount = $isTesterMode ? $this->testerAmount() : $checkoutOutstanding;

        $defaultDonation = 0.0;
        $fromTransactionId = (int) $request->query('from_transaction', 0);
        if (! $isTesterMode && $fromTransactionId > 0) {
            $previousTransaction = FamilyPaymentTransaction::query()
                ->whereKey($fromTransactionId)
                ->where('family_billing_id', $familyBilling->id)
                ->first();

            if ($previousTransaction) {
                $defaultDonation = max(0, round((float) $previousTransaction->amount - $checkoutOutstanding, 2));
            }
        }

        return view('parent.checkout', [
            'familyBilling' => $familyBilling,
            'familyChildren' => $children,
            'defaultName' => (string) ($request->user()?->name ?: $children->first()?->parent_name),
            'defaultEmail' => (string) $request->user()?->email,
            'defaultPhone' => (string) $request->user()?->phone,
            'defaultDonation' => $defaultDonation,
            'isTesterMode' => $isTesterMode,
            'testerAmount' => $this->testerAmount(),
            'checkoutBaseAmount' => $checkoutBaseAmount,
        ]);
    }

    public function create(Request $request, FamilyBilling $familyBilling): RedirectResponse
    {
        $this->authorizeParentFamilyBilling($request, $familyBilling);

        $validated = $request->validate([
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_email' => ['required', 'email', 'max:255'],
            'payer_phone' => ['required', 'string', 'max:25'],
            'donation_preset' => ['nullable', 'numeric', 'min:0'],
            'donation_custom' => ['nullable', 'numeric', 'min:0'],
        ]);

        $isTesterMode = (bool) $request->user()?->isParentTester();
        $outstanding = (float) $familyBilling->outstanding_amount;
        $donation = (float) ($validated['donation_custom'] ?: $validated['donation_preset'] ?: 0);
        $totalAmount = round($outstanding + $donation, 2);

        if ($isTesterMode) {
            $donation = 0.0;
            $totalAmount = $this->testerAmount();
        }

        if ($totalAmount <= 0) {
            return back()->withErrors([
                'donation_custom' => 'Tiada jumlah bayaran. Bil keluarga telah selesai.',
            ])->withInput();
        }

        $children = Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        $externalOrderId = 'PBG-'.strtoupper((string) Str::ulid());

        try {
            $billCode = $this->toyyibPayService->createBill([
                'billName' => "Yuran PIBG {$familyBilling->billing_year} - {$familyBilling->family_code}",
                'billDescription' => "Bayaran PIBG keluarga {$familyBilling->family_code}",
                'billPriceSetting' => 1,
                'billPayorInfo' => 1,
                'billAmount' => (int) round($totalAmount * 100),
                'billReturnUrl' => route('parent.payments.summary.return'),
                'billCallbackUrl' => route('parent.payments.toyyibpay.callback'),
                'billExternalReferenceNo' => $externalOrderId,
                'billTo' => (string) $validated['payer_name'],
                'billEmail' => (string) $validated['payer_email'],
                'billPhone' => (string) $validated['payer_phone'],
                'billSplitPayment' => 0,
                'billSplitPaymentArgs' => '',
                'billPaymentChannel' => (string) config('services.toyyibpay.payment_channel', '0'),
                'billChargeToCustomer' => (string) config('services.toyyibpay.charge_to_customer', ''),
            ]);
        } catch (RuntimeException $exception) {
            Log::warning('ToyyibPay bill creation failed', [
                'family_billing_id' => $familyBilling->id,
                'family_code' => $familyBilling->family_code,
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'payment_gateway' => $exception->getMessage(),
            ])->withInput();
        }

        FamilyPaymentTransaction::query()->create([
            'family_billing_id' => $familyBilling->id,
            'user_id' => $request->user()?->id,
            'payment_provider' => 'toyyibpay',
            'external_order_id' => $externalOrderId,
            'provider_bill_code' => $billCode,
            'amount' => $totalAmount,
            'payer_name' => $validated['payer_name'],
            'payer_email' => $validated['payer_email'],
            'payer_phone' => $validated['payer_phone'],
            'status' => 'pending',
            'return_status' => 'pending completion',
            'raw_return' => [
                'donation' => $donation,
                'outstanding_at_checkout' => $outstanding,
                'tester_mode' => $isTesterMode,
            ],
        ]);

        return redirect()->away($this->toyyibPayService->paymentUrl($billCode));
    }

    public function history(Request $request): View
    {
        $filter = (string) $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'successful', 'pending'], true)) {
            $filter = 'all';
        }

        $user = $request->user();
        $isTesterMode = (bool) $user?->isParentTester();

        $familyCodes = $this->resolveOwnedFamilyCodes((string) $user?->phone);

        $transactions = FamilyPaymentTransaction::query()
            ->with('familyBilling')
            ->when(
                $isTesterMode,
                fn ($query) => $query->where('user_id', $user?->id),
                fn ($query) => $query->whereHas('familyBilling', fn ($billingQuery) => $billingQuery->whereIn('family_code', $familyCodes))
            )
            ->when($filter === 'successful', fn ($query) => $query->where('status', 'success'))
            ->when($filter === 'pending', fn ($query) => $query->where('status', 'pending'))
            ->latest('id')
            ->paginate(20);

        return view('parent.payment-history', [
            'transactions' => $transactions,
            'activeFilter' => $filter,
        ]);
    }

    public function handleReturn(Request $request): RedirectResponse
    {
        $statusId = (string) $request->query('status_id', '');
        $billCode = (string) $request->query('billcode', '');
        $orderId = (string) $request->query('order_id', '');

        $transaction = FamilyPaymentTransaction::query()
            ->when(filled($orderId), fn ($query) => $query->where('external_order_id', $orderId))
            ->when(blank($orderId) && filled($billCode), fn ($query) => $query->where('provider_bill_code', $billCode))
            ->latest('id')
            ->first();

        if (! $transaction) {
            return redirect()->route('parent.dashboard')
                ->with('status', 'No payment transaction found for this return URL.');
        }

        $transaction->update([
            'raw_return' => $request->query(),
            'provider_bill_code' => filled($billCode) ? $billCode : $transaction->provider_bill_code,
            'status' => $statusId === '1' ? 'success' : ($statusId === '3' ? 'failed' : 'pending'),
            'return_status' => $this->mapReturnStatus(
                $statusId,
                (string) ($request->query('reason')
                    ?? $request->query('msg')
                    ?? $request->query('status_message')
                    ?? $request->query('error_desc')
                    ?? $request->query('error')
                    ?? '')
            ),
        ]);

        if ($statusId === '1') {
            $this->synchronizeSuccessfulPayment($transaction);
        } elseif ($statusId === '3') {
            $transaction->update([
                'status_reason' => 'Payment unsuccessful at gateway return.',
            ]);
        }

        return redirect()->route('parent.payments.summary', $transaction->external_order_id);
    }

    public function handleCallback(Request $request): Response
    {
        $status = (string) $request->input('status', '');
        $orderId = (string) $request->input('order_id', '');
        $refNo = (string) $request->input('refno', '');
        $hash = (string) $request->input('hash', '');
        $billCode = (string) $request->input('billcode', '');

        if (! $this->toyyibPayService->verifyCallbackHash($status, $orderId, $refNo, $hash)) {
            return response('invalid hash', 422);
        }

        $transaction = FamilyPaymentTransaction::query()
            ->where('external_order_id', $orderId)
            ->orWhere('provider_bill_code', $billCode)
            ->latest('id')
            ->first();

        if (! $transaction) {
            return response('transaction not found', 404);
        }

        $transaction->update([
            'raw_callback' => $request->all(),
            'provider_bill_code' => filled($billCode) ? $billCode : $transaction->provider_bill_code,
            'provider_ref_no' => $refNo ?: $transaction->provider_ref_no,
            'status' => $status === '1' ? 'success' : ($status === '3' ? 'failed' : 'pending'),
            'return_status' => $this->mapReturnStatus(
                $status,
                (string) ($request->input('reason')
                    ?? $request->input('msg')
                    ?? $request->input('status_message')
                    ?? $request->input('error_desc')
                    ?? $request->input('error')
                    ?? '')
            ),
            'status_reason' => (string) $request->input('reason'),
        ]);

        if ($status === '1') {
            $this->synchronizeSuccessfulPayment($transaction);
        }

        return response('ok');
    }

    public function summary(string $externalOrderId): View
    {
        $transaction = FamilyPaymentTransaction::query()
            ->with('familyBilling')
            ->where('external_order_id', $externalOrderId)
            ->firstOrFail();

        $familyChildren = Student::query()
            ->where('family_code', $transaction->familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        $receiptUrl = $this->paymentNotificationService->receiptUrl($transaction);

        return view('parent.payment-summary', [
            'transaction' => $transaction,
            'familyChildren' => $familyChildren,
            'receiptUrl' => $receiptUrl,
            'teacherShareUrl' => $this->buildTeacherShareUrl($transaction, $receiptUrl),
        ]);
    }

    public function receiptPdf(string $externalOrderId): Response
    {
        $transaction = FamilyPaymentTransaction::query()
            ->with('familyBilling')
            ->where('external_order_id', $externalOrderId)
            ->firstOrFail();

        $familyChildren = Student::query()
            ->where('family_code', $transaction->familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        $pdf = Pdf::loadView('parent.receipt-pdf', [
            'transaction' => $transaction,
            'familyChildren' => $familyChildren,
        ])->setPaper('a4');

        return $pdf->download("resit-transaksi-yuran-pibg-{$externalOrderId}.pdf");
    }

    private function synchronizeSuccessfulPayment(FamilyPaymentTransaction $transaction): void
    {
        if ($transaction->status === 'success' && $transaction->paid_at !== null) {
            return;
        }

        $billing = $transaction->familyBilling()->first();

        if (! $billing) {
            return;
        }

        $gatewayTransactions = $this->toyyibPayService->getBillTransactions((string) $transaction->provider_bill_code);

        $matched = collect($gatewayTransactions)
            ->first(fn (array $item): bool => (string) ($item['billExternalReferenceNo'] ?? '') === $transaction->external_order_id);

        $paidAmount = $this->normalizeGatewayAmount($matched['billpaymentAmount'] ?? $transaction->amount);
        $invoiceNo = (string) ($matched['billpaymentInvoiceNo'] ?? '');
        $paymentDate = (string) ($matched['billPaymentDate'] ?? '');

        DB::transaction(function () use ($transaction, $billing, $paidAmount, $invoiceNo, $paymentDate): void {
            $billing->refresh();

            $feeOutstanding = max(0, (float) $billing->fee_amount - (float) $billing->paid_amount);
            $feePaid = min($feeOutstanding, $paidAmount);
            $donation = max(0, $paidAmount - $feePaid);

            $billing->paid_amount = min((float) $billing->fee_amount, (float) $billing->paid_amount + $feePaid);
            $billing->status = ((float) $billing->paid_amount >= (float) $billing->fee_amount) ? 'paid' : 'partial';
            $billing->save();

            $transaction->update([
                'status' => 'success',
                'provider_invoice_no' => filled($invoiceNo) ? $invoiceNo : $transaction->provider_invoice_no,
                'amount' => $paidAmount,
                'fee_amount_paid' => $feePaid,
                'donation_amount' => $donation,
                'paid_at' => now(),
                'status_reason' => filled($paymentDate) ? "Paid at {$paymentDate}" : $transaction->status_reason,
            ]);
        });

        $transaction->refresh();

        if (filled($transaction->payer_phone)) {
            $billing->registerPhone((string) $transaction->payer_phone);
        }

        $this->syncParentProfileFromPaidTransaction($transaction);

        if (! $transaction->receipt_notified_at && filled($transaction->payer_phone)) {
            $parentName = Student::query()
                ->where('family_code', $billing->family_code)
                ->whereNotNull('parent_name')
                ->value('parent_name');

            try {
                $this->paymentNotificationService->sendPaymentReceipt(
                    $transaction,
                    (string) $transaction->payer_phone,
                    $parentName ? (string) $parentName : null,
                );
            } catch (\Throwable $exception) {
                Log::warning('Unable to send payment receipt WhatsApp notification.', [
                    'transaction_id' => $transaction->id,
                    'payer_phone' => $transaction->payer_phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function buildTeacherShareUrl(FamilyPaymentTransaction $transaction, string $receiptUrl): string
    {
        $phone = $this->normalizeWaPhone((string) config('services.teacher_whatsapp_phone', '60123103205'));

        $message = implode("\n", [
            'Assalamualaikum guru,',
            '',
            'Saya ingin kongsi resit bayaran PIBG:',
            'Kod keluarga: '.($transaction->familyBilling->family_code ?? '-'),
            'Jumlah: RM'.number_format((float) $transaction->amount, 2),
            'Order ID: '.$transaction->external_order_id,
            'Resit web: '.$receiptUrl,
        ]);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    private function normalizeWaPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '60123103205';
        }

        if (str_starts_with($digits, '60')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '6'.$digits;
        }

        if (str_starts_with($digits, '1')) {
            return '60'.$digits;
        }

        return $digits;
    }

    private function normalizeGatewayAmount(mixed $amount): float
    {
        $stringAmount = trim((string) $amount);

        if ($stringAmount === '') {
            return 0.0;
        }

        if (str_contains($stringAmount, '.')) {
            return (float) $stringAmount;
        }

        $numeric = (float) $stringAmount;

        return $numeric > 1000 ? round($numeric / 100, 2) : $numeric;
    }

    private function authorizeParentFamilyBilling(Request $request, FamilyBilling $familyBilling): void
    {
        $selectionCompleted = (bool) $request->session()->get('parent_child_selection_completed', false);
        $selectedBillingId = (int) $request->session()->get('parent_selected_family_billing_id', 0);

        abort_unless(
            $selectionCompleted && $selectedBillingId === (int) $familyBilling->id,
            403,
            'Please select your child from Carian Nama Murid before opening checkout.'
        );

        if ($request->user()?->isParentTester()) {
            return;
        }

        $ownedFamilyCodes = $this->resolveOwnedFamilyCodes((string) $request->user()?->phone);

        abort_unless($ownedFamilyCodes->contains($familyBilling->family_code), 403, 'Unauthorized family billing access.');
    }

    private function testerAmount(): float
    {
        $configured = (float) config('services.parent_tester_amount', 1);

        return round(max(0.01, $configured), 2);
    }

    /**
     * @return Collection<int, string>
     */
    private function resolveOwnedFamilyCodes(string $phone): Collection
    {
        $normalizedPhone = ParentPhone::normalizeForMatch($phone);

        if ($normalizedPhone === '') {
            return collect();
        }

        $studentFamilyCodes = Student::query()
            ->whereIn('parent_phone', ParentPhone::variants($phone))
            ->whereNotNull('family_code')
            ->pluck('family_code');

        $registeredFamilyCodes = FamilyBilling::query()
            ->whereHas('phones', fn ($query) => $query->where('normalized_phone', $normalizedPhone))
            ->pluck('family_code');

        return $studentFamilyCodes
            ->merge($registeredFamilyCodes)
            ->filter()
            ->unique()
            ->values();
    }

    private function syncParentProfileFromPaidTransaction(FamilyPaymentTransaction $transaction): void
    {
        $user = $transaction->user()->first();

        if (! $user) {
            return;
        }

        $updates = [];

        if (filled($transaction->payer_name) && $transaction->payer_name !== $user->name) {
            $updates['name'] = $transaction->payer_name;
        }

        if (filled($transaction->payer_email) && $transaction->payer_email !== $user->email) {
            $updates['email'] = $transaction->payer_email;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }

    private function mapReturnStatus(?string $statusId, ?string $reason = null): string
    {
        $normalizedStatus = trim((string) $statusId);

        if ($normalizedStatus === '1') {
            return 'successful';
        }

        if ($normalizedStatus === '' || in_array($normalizedStatus, ['0', '2', '4'], true)) {
            return 'pending completion';
        }

        $reasonText = strtolower(trim((string) $reason));

        if ($reasonText !== '') {
            if (str_contains($reasonText, 'cancel') || str_contains($reasonText, 'batal')) {
                return 'parent cancel';
            }

            if (
                str_contains($reasonText, 'not enough fund')
                || str_contains($reasonText, 'insufficient fund')
                || str_contains($reasonText, 'fund not enough')
                || str_contains($reasonText, 'saldo tidak mencukupi')
                || str_contains($reasonText, 'duit tidak cukup')
            ) {
                return 'not enough fund';
            }
        }

        return 'not successful';
    }
}

