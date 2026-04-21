<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\SiteSetting;
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

    public function checkout(Request $request, FamilyBilling $familyBilling): View|RedirectResponse
    {
        $this->authorizeParentFamilyBilling($request, $familyBilling);

        $alreadyPaidCurrentYear = $this->familyHasCompletedCurrentYearPayment($familyBilling);

        $children = Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->orderBy('full_name')
            ->get();
        $studentIds = $children->pluck('id')->filter()->values();
        $childNames = $children
            ->pluck('full_name')
            ->map(fn ($name) => $this->normalizeNameForLegacyMatch((string) $name))
            ->filter()
            ->unique()
            ->values();

        $isTesterMode = (bool) $request->user()?->isParentTester();
        $checkoutOutstanding = $alreadyPaidCurrentYear
            ? 0.0
            : (float) $familyBilling->outstanding_amount;
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

        $recentPaymentAttempts = FamilyPaymentTransaction::query()
            ->with('familyBilling')
            ->whereHas('familyBilling', fn ($query) => $query->where('family_code', $familyBilling->family_code))
            ->latest('id')
            ->limit(5)
            ->get();

        $lastYear = (int) $familyBilling->billing_year - 1;
        $portalLastYearPayments = FamilyPaymentTransaction::query()
            ->where('status', 'success')
            ->whereHas('familyBilling', fn ($query) => $query
                ->where('family_code', $familyBilling->family_code)
                ->where('billing_year', $lastYear))
            ->get();
        $portalPaymentRows = $portalLastYearPayments
            ->map(function (FamilyPaymentTransaction $transaction): array {
                $donationAmount = $this->resolveSuccessfulDonationAmount($transaction);

                return [
                    'id' => 'portal-'.$transaction->id,
                    'reference' => (string) $transaction->external_order_display,
                    'paid_amount' => round((float) $transaction->amount, 2),
                    'donation_amount' => $donationAmount,
                    'paid_at' => $transaction->paid_at_for_display ?? $transaction->created_at_for_display,
                    'source' => 'portal',
                ];
            })
            ->values();

        $legacyLastYearPayments = LegacyStudentPayment::query()
            ->where('source_year', $lastYear)
            ->where('payment_status', 'paid')
            ->where(function ($nested) use ($familyBilling, $studentIds) {
                $nested->where('family_code', $familyBilling->family_code);

                if ($studentIds->isNotEmpty()) {
                    $nested->orWhereIn('student_id', $studentIds->all());
                }
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(function (LegacyStudentPayment $payment) use ($familyBilling, $studentIds, $childNames): bool {
                if ($payment->student_id !== null && $studentIds->contains((int) $payment->student_id)) {
                    return true;
                }

                if ((string) $payment->family_code !== (string) $familyBilling->family_code) {
                    return false;
                }

                $legacyName = $this->normalizeNameForLegacyMatch((string) $payment->student_name);
                return $legacyName !== '' && $childNames->contains($legacyName);
            })
            ->values();

        $legacyPaymentRows = $legacyLastYearPayments
            ->groupBy(function (LegacyStudentPayment $payment): string {
                $reference = trim((string) $payment->payment_reference);
                if ($reference !== '') {
                    $normalizedReference = preg_replace('/\s+/', '', mb_strtoupper($reference)) ?? '';
                    return $normalizedReference !== '' ? $normalizedReference : $reference;
                }

                return 'LEGACY-ID-'.(string) $payment->id;
            })
            ->map(function (Collection $group): array {
                /** @var LegacyStudentPayment $first */
                $first = $group->first();
                $displayReference = trim((string) ($first->payment_reference ?? ''));

                return [
                    'id' => 'legacy-'.$first->id,
                    'reference' => $displayReference !== '' ? $displayReference : ('LEGACY-'.$first->id),
                    'paid_amount' => round((float) $group->max(fn (LegacyStudentPayment $payment): float => (float) ($payment->amount_paid ?? 0)), 2),
                    'donation_amount' => round((float) $group->max(fn (LegacyStudentPayment $payment): float => (float) ($payment->donation_amount ?? 0)), 2),
                    'paid_at' => $group->pluck('paid_at')->filter()->sort()->first() ?? $first->paid_at,
                    'source' => 'legacy',
                ];
            })
            ->values();

        $lastYearPaymentHistory = $portalPaymentRows
            ->concat($legacyPaymentRows)
            ->sortByDesc(fn (array $item): int => $item['paid_at']?->getTimestamp() ?? 0)
            ->take(5)
            ->values();

        $lastYearPaidTotal = (float) $portalPaymentRows->sum('paid_amount')
            + (float) $legacyPaymentRows->sum('paid_amount');
        $lastYearContributionTotal = (float) $portalPaymentRows->sum('donation_amount')
            + (float) $legacyPaymentRows->sum('donation_amount');

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
            'alreadyPaidCurrentYear' => $alreadyPaidCurrentYear,
            'recentPaymentAttempts' => $recentPaymentAttempts,
            'lastYear' => $lastYear,
            'lastYearPaidTotal' => $lastYearPaidTotal,
            'lastYearContributionTotal' => $lastYearContributionTotal,
            'lastYearPaymentHistory' => $lastYearPaymentHistory,
        ]);
    }

    private function resolveSuccessfulDonationAmount(FamilyPaymentTransaction $transaction): float
    {
        $storedDonation = (float) ($transaction->donation_amount ?? 0);
        if ($storedDonation > 0) {
            return round($storedDonation, 2);
        }

        $derived = (float) $transaction->amount - (float) ($transaction->fee_amount_paid ?? 0);
        return round(max(0, $derived), 2);
    }

    private function normalizeNameForLegacyMatch(string $name): string
    {
        $value = mb_strtoupper(trim($name));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim((string) $value);
    }

    private function familyHasCompletedCurrentYearPayment(FamilyBilling $familyBilling): bool
    {
        $currentYear = (int) now()->year;
        $billingYear = (int) $familyBilling->billing_year;
        $billingOutstanding = (float) $familyBilling->outstanding_amount;
        $billingStatus = (string) $familyBilling->status;

        if ($billingYear === $currentYear && ($billingOutstanding <= 0 || $billingStatus === 'paid')) {
            return true;
        }

        $familyCode = (string) $familyBilling->family_code;

        $hasPaidCurrentYearBilling = FamilyBilling::query()
            ->where('family_code', $familyCode)
            ->where('billing_year', $currentYear)
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidCurrentYearBilling) {
            return true;
        }

        return FamilyPaymentTransaction::query()
            ->where('status', 'success')
            ->whereHas('familyBilling', fn ($query) => $query
                ->where('family_code', $familyCode)
                ->where('billing_year', $currentYear))
            ->exists();
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
            'donation_intention' => ['nullable', 'string', 'max:500'],
        ]);

        $payerName = trim((string) ($validated['payer_name'] ?? ''));
        $payerEmail = mb_strtolower(trim((string) ($validated['payer_email'] ?? '')));

        $placeholderNamePattern = '/^parent\s+ssp-/i';
        $isPlaceholderName = preg_match($placeholderNamePattern, $payerName) === 1;
        $isPlaceholderEmail = str_ends_with($payerEmail, '@placeholder.local');

        if ($isPlaceholderName || $isPlaceholderEmail) {
            return back()->withErrors([
                'payer_name' => 'Sila pastikan nama ibu/bapa/penjaga yang dimasukkan adalah tepat dan sebenar sebelum meneruskan pembayaran.',
                'payer_email' => 'Sila gunakan alamat email yang sah dan aktif.',
            ])->withInput();
        }

        $isTesterMode = (bool) $request->user()?->isParentTester();
        $alreadyPaidCurrentYear = $this->familyHasCompletedCurrentYearPayment($familyBilling);
        $outstanding = $alreadyPaidCurrentYear
            ? 0.0
            : (float) $familyBilling->outstanding_amount;
        $donation = (float) ($validated['donation_custom'] ?: $validated['donation_preset'] ?: 0);
        $donationIntention = trim((string) ($validated['donation_intention'] ?? ''));
        $totalAmount = round($outstanding + $donation, 2);

        if ($isTesterMode) {
            $donation = 0.0;
            $totalAmount = $this->testerAmount();
        }

        if ($totalAmount <= 0) {
            return back()->withErrors([
                'donation_custom' => $alreadyPaidCurrentYear
                    ? 'Sila masukkan amaun sumbangan tambahan untuk meneruskan pembayaran.'
                    : 'Tiada jumlah bayaran. Bil keluarga telah selesai.',
            ])->withInput();
        }

        $children = Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        $externalOrderId = $this->generateExternalOrderId();

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
            'donation_intention' => $donationIntention !== '' ? $donationIntention : null,
            'status' => 'pending',
            'return_status' => 'pending completion',
            'raw_return' => [
                'donation' => $donation,
                'outstanding_at_checkout' => $outstanding,
                'tester_mode' => $isTesterMode,
                'donation_intention' => $donationIntention !== '' ? $donationIntention : null,
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

        $schoolLogoUrl = $this->schoolLogoUrl();
        $schoolLogoPdfSource = $this->schoolLogoPdfSource($schoolLogoUrl);

        $pdf = Pdf::loadView('parent.receipt-pdf', [
            'transaction' => $transaction,
            'familyChildren' => $familyChildren,
            'schoolLogoUrl' => $schoolLogoUrl,
            'schoolLogoPdfSource' => $schoolLogoPdfSource,
        ])->setPaper('a4');

        return $pdf->download("resit-transaksi-yuran-pibg-{$externalOrderId}.pdf");
    }

    private function schoolLogoUrl(): string
    {
        $settings = SiteSetting::getMany([
            'school_logo_url' => asset('images/sksp-logo.png'),
        ]);

        $logoUrl = trim((string) ($settings['school_logo_url'] ?? ''));

        return $logoUrl !== '' ? $logoUrl : asset('images/sksp-logo.png');
    }

    private function schoolLogoPdfSource(string $schoolLogoUrl): string
    {
        $path = parse_url($schoolLogoUrl, PHP_URL_PATH);

        if (is_string($path) && trim($path) !== '') {
            $resolved = public_path(ltrim($path, '/'));
            if (is_file($resolved)) {
                return $resolved;
            }
        }

        return public_path('images/sksp-logo.png');
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
            $feeOutstandingAtCheckout = max(0, (float) data_get($transaction->raw_return, 'outstanding_at_checkout', $feeOutstanding));
            $feeOutstanding = min($feeOutstanding, $feeOutstandingAtCheckout);
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

        try {
            $teacherDeliveries = $this->paymentNotificationService->sendTeacherClassNotifications($transaction);

            if ($teacherDeliveries !== []) {
                Log::info('Teacher WhatsApp notifications sent for successful payment.', [
                    'transaction_id' => $transaction->id,
                    'teacher_count' => count($teacherDeliveries),
                    'teachers' => collect($teacherDeliveries)->map(fn (array $row) => [
                        'teacher_id' => $row['teacher_id'] ?? null,
                        'teacher_class' => $row['teacher_class'] ?? null,
                    ])->values()->all(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Unable to send teacher WhatsApp notifications.', [
                'transaction_id' => $transaction->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }


    private function generateExternalOrderId(): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = FamilyPaymentTransaction::makeCompactExternalOrderId();

            if (! FamilyPaymentTransaction::query()->where('external_order_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'PBG-'.strtoupper((string) Str::ulid());
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
            'Order ID: '.$transaction->external_order_display,
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
