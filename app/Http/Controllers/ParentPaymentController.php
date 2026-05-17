<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\PaymentAllocation;
use App\Models\PaymentGatewaySetting;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Support\ParentPhone;
use App\Services\FamilyPaymentPlanService;
use App\Services\FamilyPaymentSettlementService;
use App\Services\PaymentCampaignService;
use App\Services\ParentPaymentNotificationService;
use App\Services\ToyyibPayService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class ParentPaymentController extends Controller
{
    public function __construct(
        private readonly ToyyibPayService $toyyibPayService,
        private readonly FamilyPaymentPlanService $paymentPlanService,
        private readonly FamilyPaymentSettlementService $paymentSettlementService,
        private readonly PaymentCampaignService $paymentCampaignService,
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
        $paymentPlan = $familyBilling->paymentPlan()
            ->with(['installments' => fn ($query) => $query->orderBy('installment_no')])
            ->first();
        $paymentPlanProgress = $paymentPlan && (float) $paymentPlan->total_amount > 0
            ? (int) round(((float) $paymentPlan->paid_amount / (float) $paymentPlan->total_amount) * 100)
            : 0;
        $canChangePaymentPlan = $paymentPlan
            ? $this->paymentPlanService->canChangePlan($paymentPlan->loadMissing('installments.transactions'))
            : false;
        $forcePlanSelection = $request->boolean('select_plan') && $canChangePaymentPlan;
        $campaignSetting = $this->paymentCampaignService->activeCampaign();
        $familySocialTag = $this->paymentCampaignService->resolveFamilySocialTag($familyBilling);
        $eligiblePlanTypes = $this->paymentCampaignService->eligiblePlanTypes($familyBilling);
        $availablePlans = $this->paymentPlanService->availablePlans((float) $familyBilling->fee_amount, $eligiblePlanTypes);
        $availablePaymentOptionLabels = $this->paymentCampaignService->eligiblePlanLabels($familyBilling);
        $paymentGatewaySetting = PaymentGatewaySetting::current();

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
            'paymentPlan' => $paymentPlan,
            'paymentPlanProgress' => $paymentPlanProgress,
            'canChangePaymentPlan' => $canChangePaymentPlan,
            'forcePlanSelection' => $forcePlanSelection,
            'availablePlans' => $availablePlans,
            'campaignSetting' => $campaignSetting,
            'paymentGatewaySetting' => $paymentGatewaySetting,
            'familySocialTag' => $familySocialTag,
            'availablePaymentOptionLabels' => $availablePaymentOptionLabels,
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

        $existingPlan = $familyBilling->paymentPlan()->first();
        $eligiblePlanTypes = $this->paymentCampaignService->eligiblePlanTypes($familyBilling);

        if ($existingPlan && (float) $familyBilling->outstanding_amount > 0 && $existingPlan->status !== FamilyPaymentPlan::STATUS_PAID) {
            return redirect()
                ->route('parent.payments.checkout', $familyBilling)
                ->withErrors([
                    'payment_plan' => 'Sila teruskan bayaran melalui pelan ansuran yang telah dipilih.',
                ]);
        }

        if ((float) $familyBilling->outstanding_amount > 0 && ! in_array(FamilyPaymentPlan::PLAN_FULL, $eligiblePlanTypes, true)) {
            return redirect()
                ->route('parent.payments.checkout', $familyBilling)
                ->withErrors([
                    'payment_plan' => 'Bayaran penuh tidak dibenarkan untuk kempen semasa.',
                ]);
        }

        $validated = $this->validatePayerInput($request, true);

        $isTesterMode = (bool) $request->user()?->isParentTester();
        $alreadyPaidCurrentYear = $this->familyHasCompletedCurrentYearPayment($familyBilling);
        $outstanding = $alreadyPaidCurrentYear
            ? 0.0
            : (float) $familyBilling->outstanding_amount;
        $donation = $this->resolveFullPaymentDonation($validated);
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
        $paymentGatewaySetting = PaymentGatewaySetting::current();
        $this->supersedePendingTransactions($familyBilling);
        $billDescription = $this->buildToyyibPayBillDescription(
            sprintf('Yuran PIBG %d', (int) $familyBilling->billing_year),
            $outstanding,
            $donation
        );

        try {
            $billCode = $this->toyyibPayService->createBill(array_merge([
                'billName' => "Yuran PIBG {$familyBilling->billing_year} - {$familyBilling->family_code}",
                'billDescription' => $billDescription,
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
                'billChargeToCustomer' => (string) config('services.toyyibpay.charge_to_customer', ''),
            ], $paymentGatewaySetting->toToyyibPayPayload()));
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

        $transaction = FamilyPaymentTransaction::query()->create([
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

        $this->createPaymentAllocations(
            $transaction,
            $transaction->provider_bill_code,
            $externalOrderId,
            min($outstanding, $totalAmount),
            $donation
        );

        return redirect()->away($this->toyyibPayService->paymentUrl($billCode));
    }

    public function selectPlan(Request $request, FamilyBilling $familyBilling): RedirectResponse
    {
        $this->authorizeParentFamilyBilling($request, $familyBilling);

        $validated = $request->validate([
            'plan_type' => ['required', 'in:full,two_times,three_times'],
        ]);

        if ((float) $familyBilling->outstanding_amount <= 0) {
            return redirect()
                ->route('parent.payments.checkout', $familyBilling)
                ->withErrors([
                    'payment_plan' => 'Bil keluarga ini telah selesai. Pelan ansuran tidak diperlukan.',
                ]);
        }

        $planType = (string) $validated['plan_type'];
        $eligiblePlanTypes = $this->paymentCampaignService->eligiblePlanTypes($familyBilling);

        if (! in_array($planType, $eligiblePlanTypes, true)) {
            return redirect()
                ->route('parent.payments.checkout', $familyBilling)
                ->withErrors([
                    'payment_plan' => 'Pelan bayaran yang dipilih tidak dibenarkan untuk keluarga ini dalam kempen semasa.',
                ]);
        }

        $existingPlan = $familyBilling->paymentPlan()->with('installments.transactions')->first();

        if ($existingPlan) {
            if ((string) $existingPlan->plan_type === $planType) {
                return redirect()
                    ->route('parent.payments.checkout', $familyBilling)
                    ->with('status', 'Pelan bayaran ini telah dipilih untuk keluarga anda.');
            }

            if (! $this->paymentPlanService->canChangePlan($existingPlan)) {
                return redirect()
                    ->route('parent.payments.checkout', $familyBilling)
                    ->withErrors([
                        'payment_plan' => 'Pilihan bayaran tidak boleh ditukar kerana bayaran telah dibuat.',
                    ]);
            }

            $this->paymentPlanService->cancelPlanForParentChange($existingPlan);
        }

        $this->paymentPlanService->createPlan($familyBilling, $planType);

        return redirect()
            ->route('parent.payments.checkout', $familyBilling)
            ->with('status', 'Pelan bayaran berjaya dipilih.');
    }

    public function payInstallment(Request $request, FamilyPaymentInstallment $installment): RedirectResponse
    {
        $familyBilling = $installment->familyBilling()->firstOrFail();
        $this->authorizeParentFamilyBilling($request, $familyBilling);

        $validated = $this->validatePayerInput($request, false);
        $donation = $this->resolveInstallmentDonation($request, $installment);

        $installment->loadMissing('paymentPlan.installments');

        $validationMessage = $this->paymentPlanService->validateInstallmentCanBePaid($installment);

        if ($validationMessage !== null) {
            return redirect()
                ->route('parent.payments.checkout', $familyBilling)
                ->withErrors([
                    'payment_plan' => $validationMessage,
                ])
                ->withInput();
        }

        $externalOrderId = $this->generateExternalOrderId();
        $paymentGatewaySetting = PaymentGatewaySetting::current();
        $this->supersedePendingTransactions($familyBilling, $installment);
        $totalAmount = round((float) $installment->amount + $donation, 2);
        $billDescription = $this->buildToyyibPayBillDescription(
            sprintf(
                'Ansuran %d PIBG %d',
                (int) $installment->installment_no,
                (int) $familyBilling->billing_year
            ),
            (float) $installment->amount,
            $donation
        );

        try {
            $billCode = $this->toyyibPayService->createBill(array_merge([
                'billName' => sprintf(
                    'Ansuran %d PIBG %d - %s',
                    (int) $installment->installment_no,
                    (int) $familyBilling->billing_year,
                    (string) $familyBilling->family_code
                ),
                'billDescription' => sprintf(
                    '%s untuk keluarga %s',
                    $billDescription,
                    (string) $familyBilling->family_code
                ),
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
                'billChargeToCustomer' => (string) config('services.toyyibpay.charge_to_customer', ''),
            ], $paymentGatewaySetting->toToyyibPayPayload()));
        } catch (RuntimeException $exception) {
            Log::warning('ToyyibPay installment bill creation failed', [
                'family_billing_id' => $familyBilling->id,
                'installment_id' => $installment->id,
                'family_code' => $familyBilling->family_code,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('parent.payments.checkout', $familyBilling)
                ->withErrors([
                    'payment_gateway' => $exception->getMessage(),
                ])
                ->withInput();
        }

        $transaction = FamilyPaymentTransaction::query()->create([
            'family_billing_id' => $familyBilling->id,
            'family_payment_installment_id' => $installment->id,
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
                'installment_id' => $installment->id,
                'plan_type' => $installment->paymentPlan?->plan_type,
                'installment_no' => $installment->installment_no,
                'installment_amount' => (float) $installment->amount,
                'donation' => $donation,
                'outstanding_at_checkout' => (float) $installment->amount,
            ],
        ]);

        $this->createPaymentAllocations(
            $transaction,
            $billCode,
            $externalOrderId,
            (float) $installment->amount,
            $donation
        );

        $installment->forceFill([
            'status' => FamilyPaymentInstallment::STATUS_REDIRECTED,
            'billcode' => $billCode,
        ])->save();

        $this->paymentPlanService->syncInstallmentAttemptStatus($transaction, 'pending');

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

        $latestSuccessfulTransaction = FamilyPaymentTransaction::query()
            ->with('familyBilling')
            ->when(
                $isTesterMode,
                fn ($query) => $query->where('user_id', $user?->id),
                fn ($query) => $query->whereHas('familyBilling', fn ($billingQuery) => $billingQuery->whereIn('family_code', $familyCodes))
            )
            ->where('status', 'success')
            ->latest('id')
            ->first();

        $lastYear = (int) now()->year - 1;
        $legacyPayments = LegacyStudentPayment::query()
            ->where('source_year', $lastYear)
            ->where('payment_status', 'paid')
            ->when(
                $isTesterMode,
                fn ($query) => $query->whereIn('family_code', $familyCodes),
                fn ($query) => $query->whereIn('family_code', $familyCodes)
            )
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        return view('parent.payment-history', [
            'transactions' => $transactions,
            'activeFilter' => $filter,
            'latestSuccessfulTransaction' => $latestSuccessfulTransaction,
            'legacyPayments' => $legacyPayments,
            'lastYear' => $lastYear,
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

        if ($this->shouldIgnoreGatewayUpdate($transaction)) {
            $transaction->update([
                'raw_return' => $request->query(),
                'provider_bill_code' => filled($billCode) ? $billCode : $transaction->provider_bill_code,
            ]);

            return redirect()
                ->route('parent.payments.checkout', $transaction->familyBilling)
                ->with('status', 'Cubaan bayaran lama telah dibatalkan. Sila gunakan pelan bayaran terkini.');
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
        $this->paymentSettlementService->syncTransactionAllocationStatus($transaction->fresh(['allocations']));

        if ($statusId === '1') {
            $this->synchronizeSuccessfulPayment($transaction);
        } elseif ($statusId === '3') {
            $transaction->update([
                'status_reason' => 'Payment unsuccessful at gateway return.',
            ]);
        }

        $this->paymentPlanService->syncInstallmentAttemptStatus(
            $transaction->fresh(['installment.paymentPlan']),
            $this->resolveInstallmentAttemptStatus($transaction->fresh())
        );

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

        if ($this->shouldIgnoreGatewayUpdate($transaction)) {
            $transaction->update([
                'raw_callback' => $request->all(),
                'provider_bill_code' => filled($billCode) ? $billCode : $transaction->provider_bill_code,
                'provider_ref_no' => $refNo ?: $transaction->provider_ref_no,
            ]);

            return response('ok');
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
        $this->paymentSettlementService->syncTransactionAllocationStatus($transaction->fresh(['allocations']));

        if ($status === '1') {
            $this->synchronizeSuccessfulPayment($transaction);
        }

        $this->paymentPlanService->syncInstallmentAttemptStatus(
            $transaction->fresh(['installment.paymentPlan']),
            $this->resolveInstallmentAttemptStatus($transaction->fresh())
        );

        return response('ok');
    }

    public function summary(string $externalOrderId): View
    {
        $transaction = FamilyPaymentTransaction::query()
            ->with(['familyBilling', 'installment.paymentPlan.installments'])
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
            'receiptContext' => $this->buildReceiptContext($transaction),
            'teacherShareUrl' => $this->buildTeacherShareUrl($transaction, $receiptUrl),
        ]);
    }

    public function receiptPdf(string $externalOrderId): Response
    {
        $transaction = FamilyPaymentTransaction::query()
            ->with(['familyBilling', 'installment.paymentPlan.installments'])
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
            'receiptContext' => $this->buildReceiptContext($transaction),
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

        $this->paymentSettlementService->synchronizeSuccessfulPayment($transaction, [
            'billpaymentAmount' => $paidAmount,
            'billpaymentInvoiceNo' => $invoiceNo,
            'billPaymentDate' => $paymentDate,
        ]);

        $transaction->refresh();
        $billing->refresh();

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
        $receiptContext = $this->buildReceiptContext($transaction);
        $installmentLine = $receiptContext['installment_label'] !== null
            ? 'Ansuran: '.$receiptContext['installment_label']
            : null;

        $lines = [
            'Assalamualaikum guru,',
            '',
            'Saya ingin kongsi resit bayaran PIBG:',
            'Kod keluarga: '.($transaction->familyBilling->family_code ?? '-'),
            'Jumlah: RM'.number_format((float) $transaction->amount, 2),
            'Order ID: '.$transaction->external_order_display,
            'Resit web: '.$receiptUrl,
        ];

        if ($installmentLine) {
            array_splice($lines, 5, 0, [$installmentLine]);
        }

        $message = implode("\n", $lines);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    private function resolveFullPaymentDonation(array $validated): float
    {
        return round(max(
            0,
            (float) ($validated['donation_custom'] ?: $validated['donation_preset'] ?: 0)
        ), 2);
    }

    private function resolveInstallmentDonation(Request $request, FamilyPaymentInstallment $installment): float
    {
        $choice = (string) data_get($request->input('installment_donation_choice', []), $installment->id, '0');
        $custom = data_get($request->input('installment_donation_custom', []), $installment->id);
        $allowedChoices = ['0', '10', '20', '50', 'other'];

        if (! in_array($choice, $allowedChoices, true)) {
            throw ValidationException::withMessages([
                "installment_donation_choice.{$installment->id}" => 'Pilihan sumbangan tambahan tidak sah.',
            ]);
        }

        if ($choice === 'other') {
            if ($custom === null || $custom === '') {
                throw ValidationException::withMessages([
                    "installment_donation_custom.{$installment->id}" => 'Sila masukkan amaun sumbangan tambahan.',
                ]);
            }

            if (! is_numeric($custom)) {
                throw ValidationException::withMessages([
                    "installment_donation_custom.{$installment->id}" => 'Amaun sumbangan tambahan mesti nombor.',
                ]);
            }

            $amount = round((float) $custom, 2);

            if ($amount < 1 || $amount > 1000) {
                throw ValidationException::withMessages([
                    "installment_donation_custom.{$installment->id}" => 'Amaun sumbangan tambahan mestilah antara RM1 dan RM1000.',
                ]);
            }

            return $amount;
        }

        return round(max(0, (float) $choice), 2);
    }

    private function buildToyyibPayBillDescription(string $baseDescription, float $yuranAmount, float $donationAmount): string
    {
        $description = sprintf('%s: RM%s', $baseDescription, number_format($yuranAmount, 2, '.', ''));

        if ($donationAmount > 0) {
            $description .= sprintf(' + Sumbangan Tambahan: RM%s', number_format($donationAmount, 2, '.', ''));
        }

        return $description;
    }

    private function createPaymentAllocations(
        FamilyPaymentTransaction $transaction,
        ?string $billCode,
        string $orderId,
        float $yuranAmount,
        float $donationAmount
    ): void {
        $billcode = filled($billCode) ? (string) $billCode : null;

        if ($yuranAmount > 0) {
            PaymentAllocation::query()->create([
                'family_payment_transaction_id' => $transaction->id,
                'family_payment_installment_id' => $transaction->family_payment_installment_id,
                'family_billing_id' => $transaction->family_billing_id,
                'billcode' => $billcode,
                'order_id' => $orderId,
                'allocation_type' => PaymentAllocation::TYPE_YURAN,
                'amount' => round($yuranAmount, 2),
                'status' => PaymentAllocation::STATUS_PENDING,
            ]);
        }

        if ($donationAmount > 0) {
            PaymentAllocation::query()->create([
                'family_payment_transaction_id' => $transaction->id,
                'family_payment_installment_id' => $transaction->family_payment_installment_id,
                'family_billing_id' => $transaction->family_billing_id,
                'billcode' => $billcode,
                'order_id' => $orderId,
                'allocation_type' => PaymentAllocation::TYPE_SUMBANGAN_TAMBAHAN,
                'amount' => round($donationAmount, 2),
                'status' => PaymentAllocation::STATUS_PENDING,
            ]);
        }
    }

    private function supersedePendingTransactions(FamilyBilling $familyBilling, ?FamilyPaymentInstallment $installment = null): void
    {
        $pendingTransactions = FamilyPaymentTransaction::query()
            ->with('allocations')
            ->where('family_billing_id', $familyBilling->id)
            ->where('status', 'pending')
            ->when(
                $installment,
                fn ($query) => $query->where('family_payment_installment_id', $installment->id),
                fn ($query) => $query->whereNull('family_payment_installment_id')
            )
            ->get();

        foreach ($pendingTransactions as $pendingTransaction) {
            $pendingTransaction->forceFill([
                'status' => 'superseded',
                'return_status' => 'superseded',
                'status_reason' => 'replaced_by_new_bill',
            ])->save();

            $pendingTransaction->allocations()
                ->where('status', PaymentAllocation::STATUS_PENDING)
                ->update([
                    'status' => PaymentAllocation::STATUS_CANCELLED,
                ]);
        }
    }

    /**
     * @return array{payer_name: string, payer_email: string, payer_phone: string, donation_preset?: mixed, donation_custom?: mixed, donation_intention?: mixed}
     */
    private function validatePayerInput(Request $request, bool $includeDonationFields): array
    {
        $rules = [
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_email' => ['required', 'email', 'max:255'],
            'payer_phone' => ['required', 'string', 'max:25'],
        ];

        if ($includeDonationFields) {
            $rules['donation_preset'] = ['nullable', 'numeric', 'min:0'];
            $rules['donation_custom'] = ['nullable', 'numeric', 'min:0'];
            $rules['donation_intention'] = ['nullable', 'string', 'max:500'];
        }

        /** @var array{payer_name: string, payer_email: string, payer_phone: string, donation_preset?: mixed, donation_custom?: mixed, donation_intention?: mixed} $validated */
        $validated = $request->validate($rules);

        $payerName = trim((string) ($validated['payer_name'] ?? ''));
        $payerEmail = mb_strtolower(trim((string) ($validated['payer_email'] ?? '')));

        $placeholderNamePattern = '/^parent\s+ssp-/i';
        $isPlaceholderName = preg_match($placeholderNamePattern, $payerName) === 1;
        $isPlaceholderEmail = str_ends_with($payerEmail, '@placeholder.local');

        if ($isPlaceholderName || $isPlaceholderEmail) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payer_name' => 'Sila pastikan nama ibu/bapa/penjaga yang dimasukkan adalah tepat dan sebenar sebelum meneruskan pembayaran.',
                'payer_email' => 'Sila gunakan alamat email yang sah dan aktif.',
            ]);
        }

        return $validated;
    }

    private function resolveInstallmentAttemptStatus(FamilyPaymentTransaction $transaction): string
    {
        $returnStatus = strtolower(trim((string) $transaction->return_status));

        if ($transaction->status === 'success') {
            return 'success';
        }

        if ($transaction->status === 'failed' && (str_contains($returnStatus, 'cancel') || str_contains($returnStatus, 'batal'))) {
            return 'cancelled';
        }

        return (string) $transaction->status;
    }

    private function shouldIgnoreGatewayUpdate(FamilyPaymentTransaction $transaction): bool
    {
        $status = strtolower((string) $transaction->status);

        if ($status === 'superseded') {
            return true;
        }

        return $status === 'cancelled'
            && (string) $transaction->status_reason === 'parent_changed_payment_plan';
    }

    /**
     * @return array{
     *   has_installment: bool,
     *   plan_label: string|null,
     *   installment_label: string|null,
     *   installment_no: int|null,
     *   installment_count: int|null,
     *   transaction_amount: float,
     *   yuran_paid_this_transaction: float,
     *   donation_paid_this_transaction: float,
     *   total_paid_to_date: float|null,
     *   remaining_balance: float|null,
     *   payment_status_label: string|null,
     *   fully_paid: bool
     * }
     */
    private function buildReceiptContext(FamilyPaymentTransaction $transaction): array
    {
        $transaction->loadMissing('installment.paymentPlan.installments');

        $installment = $transaction->installment;
        $plan = $installment?->paymentPlan;

        if (! $installment || ! $plan) {
            $billing = $transaction->familyBilling;
            $billingFeeAmount = (float) ($billing?->fee_amount ?? 0);
            $billingPaidAmount = (float) ($billing?->paid_amount ?? 0);
            $yuranPaid = (float) ($transaction->fee_amount_paid ?? min((float) $transaction->amount, $billingFeeAmount));
            $donationPaid = (float) ($transaction->donation_amount ?? max(0, (float) $transaction->amount - $yuranPaid));

            return [
                'has_installment' => false,
                'plan_label' => null,
                'installment_label' => null,
                'installment_no' => null,
                'installment_count' => null,
                'transaction_amount' => round((float) $transaction->amount, 2),
                'yuran_paid_this_transaction' => round($yuranPaid, 2),
                'donation_paid_this_transaction' => round($donationPaid, 2),
                'total_paid_to_date' => round($billingPaidAmount, 2),
                'remaining_balance' => round((float) ($billing?->outstanding_amount ?? 0), 2),
                'payment_status_label' => $billing && $billing->outstanding_amount <= 0 ? 'Selesai Dibayar' : ((float) $billingPaidAmount > 0 ? 'Bayaran Sebahagian' : ucfirst((string) $transaction->status)),
                'fully_paid' => $billing ? (float) $billing->outstanding_amount <= 0 : strtolower((string) $transaction->status) === 'success',
            ];
        }

        $installmentCount = $plan->installments->count();
        $fullyPaid = (float) $plan->balance_amount <= 0.0;

        return [
            'has_installment' => true,
            'plan_label' => $plan->plan_label,
            'installment_label' => sprintf('Ansuran %d/%d', (int) $installment->installment_no, $installmentCount),
            'installment_no' => (int) $installment->installment_no,
            'installment_count' => $installmentCount,
            'transaction_amount' => round((float) $transaction->amount, 2),
            'yuran_paid_this_transaction' => round((float) ($transaction->fee_amount_paid ?? 0), 2),
            'donation_paid_this_transaction' => round((float) ($transaction->donation_amount ?? 0), 2),
            'total_paid_to_date' => round((float) $plan->paid_amount, 2),
            'remaining_balance' => round((float) $plan->balance_amount, 2),
            'payment_status_label' => $fullyPaid ? 'Selesai Dibayar' : 'Bayaran Sebahagian',
            'fully_paid' => $fullyPaid,
        ];
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

        if ($request->user()?->isParentTester()) {
            return;
        }

        $ownedFamilyCodes = $this->resolveOwnedFamilyCodes((string) $request->user()?->phone);

        if (! ($selectionCompleted && $selectedBillingId === (int) $familyBilling->id)) {
            abort_unless(
                $ownedFamilyCodes->contains($familyBilling->family_code),
                403,
                'Please select your child from Carian Nama Murid before opening checkout.'
            );

            $request->session()->put('parent_selected_family_billing_id', (int) $familyBilling->id);
            $request->session()->put('parent_child_selection_completed', true);

            return;
        }

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
