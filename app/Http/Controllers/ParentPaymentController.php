<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Services\ToyyibPayService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ParentPaymentController extends Controller
{
    public function __construct(private readonly ToyyibPayService $toyyibPayService)
    {
    }

    public function checkout(Request $request, FamilyBilling $familyBilling): View
    {
        $this->authorizeParentFamilyBilling($request, $familyBilling);

        $children = Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        return view('parent.checkout', [
            'familyBilling' => $familyBilling,
            'children' => $children,
            'defaultEmail' => (string) $request->user()?->email,
            'defaultPhone' => (string) $request->user()?->phone,
        ]);
    }

    public function create(Request $request, FamilyBilling $familyBilling): RedirectResponse
    {
        $this->authorizeParentFamilyBilling($request, $familyBilling);

        $validated = $request->validate([
            'payer_email' => ['required', 'email', 'max:255'],
            'payer_phone' => ['required', 'string', 'max:25'],
            'donation_preset' => ['nullable', 'numeric', 'min:0'],
            'donation_custom' => ['nullable', 'numeric', 'min:0'],
        ]);

        $outstanding = $familyBilling->outstanding_amount;
        $donation = (float) ($validated['donation_custom'] ?: $validated['donation_preset'] ?: 0);
        $totalAmount = round($outstanding + $donation, 2);

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

        $billCode = $this->toyyibPayService->createBill([
            'billName' => "Yuran PIBG {$familyBilling->billing_year} - {$familyBilling->family_code}",
            'billDescription' => "Bayaran PIBG keluarga {$familyBilling->family_code}",
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => (int) round($totalAmount * 100),
            'billReturnUrl' => route('payments.summary.return'),
            'billCallbackUrl' => route('payments.toyyibpay.callback'),
            'billExternalReferenceNo' => $externalOrderId,
            'billTo' => (string) ($children->first()?->parent_name ?? $request->user()?->name),
            'billEmail' => (string) $validated['payer_email'],
            'billPhone' => (string) $validated['payer_phone'],
            'billSplitPayment' => 0,
            'billSplitPaymentArgs' => '',
            'billPaymentChannel' => (string) config('services.toyyibpay.payment_channel', '0'),
            'billChargeToCustomer' => (string) config('services.toyyibpay.charge_to_customer', ''),
        ]);

        FamilyPaymentTransaction::query()->create([
            'family_billing_id' => $familyBilling->id,
            'user_id' => $request->user()?->id,
            'payment_provider' => 'toyyibpay',
            'external_order_id' => $externalOrderId,
            'provider_bill_code' => $billCode,
            'amount' => $totalAmount,
            'payer_email' => $validated['payer_email'],
            'payer_phone' => $validated['payer_phone'],
            'status' => 'pending',
            'raw_return' => [
                'donation' => $donation,
                'outstanding_at_checkout' => $outstanding,
            ],
        ]);

        return redirect()->away($this->toyyibPayService->paymentUrl($billCode));
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
            'status' => $statusId === '1' ? 'success' : ($statusId === '3' ? 'failed' : 'pending'),
        ]);

        if ($statusId === '1') {
            $this->synchronizeSuccessfulPayment($transaction);
        } elseif ($statusId === '3') {
            $transaction->update([
                'status_reason' => 'Payment unsuccessful at gateway return.',
            ]);
        }

        return redirect()->route('payments.summary.show', $transaction->external_order_id);
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
            'provider_ref_no' => $refNo ?: $transaction->provider_ref_no,
            'status' => $status === '1' ? 'success' : ($status === '3' ? 'failed' : 'pending'),
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

        return view('parent.payment-summary', [
            'transaction' => $transaction,
            'familyChildren' => $familyChildren,
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

        return $pdf->download("receipt-{$externalOrderId}.pdf");
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
        $userPhone = (string) $request->user()?->phone;

        $ownedFamilyCodes = Student::query()
            ->where('parent_phone', $userPhone)
            ->whereNotNull('family_code')
            ->pluck('family_code')
            ->unique()
            ->values();

        abort_unless($ownedFamilyCodes->contains($familyBilling->family_code), 403, 'Unauthorized family billing access.');
    }
}
