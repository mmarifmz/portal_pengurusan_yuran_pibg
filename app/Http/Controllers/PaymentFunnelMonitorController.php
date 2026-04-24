<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Services\ParentPaymentNotificationService;
use App\Services\ToyyibPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentFunnelMonitorController extends Controller
{
    public function __construct(
        private readonly ToyyibPayService $toyyibPayService,
        private readonly ParentPaymentNotificationService $paymentNotificationService
    ) {
    }

    public function index(Request $request): View
    {
        $currentYear = (int) now()->year;
        $billingYear = (int) $request->integer('billing_year', $currentYear);
        if ($billingYear < 2000 || $billingYear > 2100) {
            $billingYear = $currentYear;
        }

        $search = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('gateway_status', 'all'));
        $sortBy = trim((string) $request->query('sort_by', 'timestamp'));
        $sortDir = trim((string) $request->query('sort_dir', 'desc'));
        if (! in_array($statusFilter, ['all', 'not_started', 'pending', 'failed', 'success'], true)) {
            $statusFilter = 'all';
        }
        if (! in_array($sortBy, ['parent_name', 'timestamp'], true)) {
            $sortBy = 'timestamp';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $yearOptions = FamilyBilling::query()
            ->select('billing_year')
            ->distinct()
            ->where('billing_year', '<=', $currentYear)
            ->orderByDesc('billing_year')
            ->pluck('billing_year')
            ->map(fn ($year): int => (int) $year)
            ->values();

        if ($yearOptions->isEmpty()) {
            $yearOptions = collect([$currentYear]);
        }

        if (! $yearOptions->contains($billingYear)) {
            $billingYear = (int) $yearOptions->first();
        }

        $familyBillings = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->orderBy('family_code')
            ->get();

        $studentProfilesByFamily = Student::query()
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->select(['family_code', 'parent_name', 'parent_phone'])
            ->get()
            ->groupBy('family_code');

        $latestTransactionByBillingId = FamilyPaymentTransaction::query()
            ->whereIn('family_billing_id', $familyBillings->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('family_billing_id')
            ->map(fn (Collection $transactions): ?FamilyPaymentTransaction => $transactions->first());

        $latestSuccessfulTransactionByBillingId = FamilyPaymentTransaction::query()
            ->whereIn('family_billing_id', $familyBillings->pluck('id'))
            ->where('status', 'success')
            ->orderByDesc('id')
            ->get()
            ->groupBy('family_billing_id')
            ->map(fn (Collection $transactions): ?FamilyPaymentTransaction => $transactions->first());

        $rows = $familyBillings
            ->map(function (FamilyBilling $familyBilling) use ($latestTransactionByBillingId, $latestSuccessfulTransactionByBillingId, $studentProfilesByFamily): array {
                /** @var FamilyPaymentTransaction|null $transaction */
                $transaction = $latestTransactionByBillingId->get($familyBilling->id);
                /** @var FamilyPaymentTransaction|null $successfulTransaction */
                $successfulTransaction = $latestSuccessfulTransactionByBillingId->get($familyBilling->id);
                $students = $studentProfilesByFamily->get((string) $familyBilling->family_code, collect());

                $parentName = '';
                $parentPhone = '';

                if ($transaction) {
                    $parentName = trim((string) $transaction->payer_name);
                    $parentPhone = trim((string) $transaction->payer_phone);
                }

                if (($parentName === '' || preg_match('/^parent\s+ssp-/i', $parentName) === 1) && $successfulTransaction) {
                    $successfulPayerName = trim((string) $successfulTransaction->payer_name);
                    if ($successfulPayerName !== '' && preg_match('/^parent\s+ssp-/i', $successfulPayerName) !== 1) {
                        $parentName = $successfulPayerName;
                    }
                }

                if ($parentName === '' || preg_match('/^parent\s+ssp-/i', $parentName) === 1) {
                    $parentName = (string) ($students
                        ->pluck('parent_name')
                        ->map(fn ($name): string => trim((string) $name))
                        ->filter(fn (string $name): bool => $name !== '' && preg_match('/^parent\s+ssp-/i', $name) !== 1)
                        ->first() ?? '');
                }

                if ($parentPhone === '') {
                    $parentPhone = (string) ($students
                        ->pluck('parent_phone')
                        ->map(fn ($phone): string => trim((string) $phone))
                        ->filter()
                        ->first() ?? '');
                }

                $gatewayStatus = $transaction?->status ?: 'not_started';

                $timestamp = null;
                if ($transaction) {
                    $timestampSource = $transaction->paid_at_for_display ?? $transaction->updated_at ?? $transaction->created_at;
                    $timestamp = $timestampSource
                        ? Carbon::parse($timestampSource)->timezone('Asia/Kuala_Lumpur')
                        : null;
                }

                $billCode = trim((string) ($transaction?->provider_bill_code ?? ''));

                return [
                    'family_code' => (string) $familyBilling->family_code,
                    'parent_name' => $parentName !== '' ? mb_strtoupper($parentName) : '-',
                    'phone_number' => $parentPhone !== '' ? $parentPhone : '-',
                    'billing_year' => (int) $familyBilling->billing_year,
                    'gateway_status' => $gatewayStatus,
                    'gateway_status_label' => $this->statusLabel($gatewayStatus),
                    'return_status' => $transaction?->return_status ? (string) $transaction->return_status : '-',
                    'gateway_reason' => $transaction?->status_reason ? (string) $transaction->status_reason : '-',
                    'timestamp' => $timestamp,
                    'latest_bill_code' => $billCode !== '' ? $billCode : '-',
                    'latest_transaction_id' => $transaction?->id,
                    'can_check_gateway' => $billCode !== '' && $transaction !== null,
                ];
            })
            ->values();

        if ($statusFilter !== 'all') {
            $rows = $rows->where('gateway_status', $statusFilter)->values();
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows
                ->filter(function (array $row) use ($needle): bool {
                    return str_contains(mb_strtolower((string) $row['family_code']), $needle)
                        || str_contains(mb_strtolower((string) $row['parent_name']), $needle)
                        || str_contains(mb_strtolower((string) $row['phone_number']), $needle)
                        || str_contains(mb_strtolower((string) $row['gateway_status_label']), $needle)
                        || str_contains(mb_strtolower((string) $row['return_status']), $needle)
                        || str_contains(mb_strtolower((string) $row['gateway_reason']), $needle)
                        || str_contains(mb_strtolower((string) $row['latest_bill_code']), $needle);
                })
                ->values();
        }

        $rows = $rows
            ->sort(function (array $a, array $b) use ($sortBy, $sortDir): int {
                if ($sortBy === 'parent_name') {
                    $aName = mb_strtolower((string) ($a['parent_name'] ?? ''));
                    $bName = mb_strtolower((string) ($b['parent_name'] ?? ''));

                    if ($aName !== $bName) {
                        return $sortDir === 'asc'
                            ? strcmp($aName, $bName)
                            : strcmp($bName, $aName);
                    }
                } else {
                    $aTs = $a['timestamp']?->timestamp ?? 0;
                    $bTs = $b['timestamp']?->timestamp ?? 0;

                    if ($aTs !== $bTs) {
                        return $sortDir === 'asc'
                            ? ($aTs <=> $bTs)
                            : ($bTs <=> $aTs);
                    }
                }

                return strcmp((string) ($a['family_code'] ?? ''), (string) ($b['family_code'] ?? ''));
            })
            ->values();

        return view('system.payment-funnel-monitor', [
            'rows' => $rows,
            'billingYear' => $billingYear,
            'yearOptions' => $yearOptions,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'totalFamilies' => $rows->count(),
            'successCount' => $rows->where('gateway_status', 'success')->count(),
            'pendingCount' => $rows->where('gateway_status', 'pending')->count(),
            'failedCount' => $rows->where('gateway_status', 'failed')->count(),
            'notStartedCount' => $rows->where('gateway_status', 'not_started')->count(),
        ]);
    }

    public function checkGatewayStatus(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
            'q' => ['nullable', 'string', 'max:255'],
            'billing_year' => ['nullable', 'integer'],
            'gateway_status' => ['nullable', 'string', 'max:50'],
            'sort_by' => ['nullable', 'string', 'max:50'],
            'sort_dir' => ['nullable', 'string', 'max:4'],
        ]);

        $redirectQuery = [
            'q' => (string) ($validated['q'] ?? ''),
            'billing_year' => (int) ($validated['billing_year'] ?? now()->year),
            'gateway_status' => (string) ($validated['gateway_status'] ?? 'all'),
            'sort_by' => (string) ($validated['sort_by'] ?? 'timestamp'),
            'sort_dir' => (string) ($validated['sort_dir'] ?? 'desc'),
        ];

        $transaction = FamilyPaymentTransaction::query()
            ->with('familyBilling')
            ->whereKey((int) $validated['transaction_id'])
            ->first();

        if (! $transaction) {
            return $this->gatewayCheckResponse($request, $redirectQuery, false, 'Transaction not found.', null, 404);
        }

        $billCode = trim((string) $transaction->provider_bill_code);
        if ($billCode === '') {
            return $this->gatewayCheckResponse($request, $redirectQuery, false, 'No bill code found on this transaction.', null, 422);
        }

        try {
            $gatewayTransactions = $this->toyyibPayService->getBillTransactions($billCode);
        } catch (\Throwable $exception) {
            return $this->gatewayCheckResponse($request, $redirectQuery, false, 'Gateway check failed: '.$exception->getMessage(), null, 422);
        }

        if ($gatewayTransactions === []) {
            return $this->gatewayCheckResponse(
                $request,
                $redirectQuery,
                true,
                'Gateway check completed, but no transactions returned for bill code '.$billCode.'.',
                $this->buildGatewayPayload($transaction, $billCode)
            );
        }

        $matched = collect($gatewayTransactions)
            ->first(fn (array $item): bool => (string) ($item['billExternalReferenceNo'] ?? '') === $transaction->external_order_id);

        if (! is_array($matched)) {
            $matched = (array) (collect($gatewayTransactions)->first() ?? []);
        }

        if ($matched === []) {
            return $this->gatewayCheckResponse(
                $request,
                $redirectQuery,
                true,
                'Gateway check completed, but unable to map bill response to transaction.',
                $this->buildGatewayPayload($transaction, $billCode)
            );
        }

        $gatewayStatusId = (string) ($matched['billpaymentStatus'] ?? '');
        $gatewayStatus = $this->mapGatewayStatus($gatewayStatusId);
        $gatewayReason = trim((string) (
            $matched['billpaymentStatusName']
            ?? $matched['reason']
            ?? $matched['msg']
            ?? $transaction->status_reason
            ?? ''
        ));

        $beforeStatus = (string) $transaction->status;

        if ($beforeStatus !== 'success' && $gatewayStatus === 'success') {
            $this->synchronizeSuccessfulPaymentFromGateway($transaction, $matched, $gatewayReason);
            $transaction->refresh();

            return $this->gatewayCheckResponse(
                $request,
                $redirectQuery,
                true,
                sprintf(
                    'Gateway updated %s (%s): %s -> Success. Payment synchronized and receipt notification processed.',
                    (string) ($transaction->familyBilling?->family_code ?? '-'),
                    $billCode,
                    $this->statusLabel($beforeStatus)
                ),
                $this->buildGatewayPayload($transaction, $billCode)
            );
        }

        $rawCallback = $transaction->raw_callback;
        if (! is_array($rawCallback)) {
            $rawCallback = [];
        }

        $rawCallback['gateway_check_at'] = now()->toDateTimeString();
        $rawCallback['gateway_check_bill_code'] = $billCode;
        $rawCallback['gateway_check_result'] = $matched;

        if ($beforeStatus !== 'success') {
            $transaction->forceFill([
                'status' => $gatewayStatus,
                'return_status' => $this->mapReturnStatus($gatewayStatusId, $gatewayReason),
                'status_reason' => $gatewayReason !== '' ? $gatewayReason : $transaction->status_reason,
                'provider_invoice_no' => (string) ($matched['billpaymentInvoiceNo'] ?? $transaction->provider_invoice_no),
                'raw_callback' => $rawCallback,
            ])->save();
        } else {
            $transaction->forceFill([
                'provider_invoice_no' => (string) ($matched['billpaymentInvoiceNo'] ?? $transaction->provider_invoice_no),
                'raw_callback' => $rawCallback,
            ])->save();
        }

        $transaction->refresh();

        $statusText = $beforeStatus === 'success'
            ? 'Already Success (kept as Success)'
            : $this->statusLabel((string) $transaction->status);

        return $this->gatewayCheckResponse(
            $request,
            $redirectQuery,
            true,
            sprintf(
                'Gateway check completed for %s (%s): %s',
                (string) ($transaction->familyBilling?->family_code ?? '-'),
                $billCode,
                $statusText
            ),
            $this->buildGatewayPayload($transaction, $billCode)
        );
    }

    private function gatewayCheckResponse(
        Request $request,
        array $redirectQuery,
        bool $ok,
        string $message,
        ?array $payload = null,
        int $statusCode = 200
    ): JsonResponse|RedirectResponse {
        $wantsJson = $request->expectsJson() || $request->ajax();

        if ($wantsJson) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'payload' => $payload,
            ], $statusCode);
        }

        $flashKey = $ok ? 'status' : 'error';

        return redirect()
            ->route('system.payment-funnel-monitor.index', $redirectQuery)
            ->with($flashKey, $message);
    }

    private function buildGatewayPayload(FamilyPaymentTransaction $transaction, string $billCode): array
    {
        $timestampSource = $transaction->paid_at_for_display ?? $transaction->updated_at ?? $transaction->created_at;
        $timestamp = $timestampSource
            ? Carbon::parse($timestampSource)->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i:s')
            : '-';

        return [
            'transaction_id' => $transaction->id,
            'family_code' => (string) ($transaction->familyBilling?->family_code ?? '-'),
            'gateway_status' => (string) $transaction->status,
            'gateway_status_label' => $this->statusLabel((string) $transaction->status),
            'return_status' => (string) ($transaction->return_status ?: '-'),
            'gateway_reason' => (string) ($transaction->status_reason ?: '-'),
            'timestamp' => $timestamp,
            'latest_bill_code' => $billCode,
            'can_check_gateway' => $billCode !== '',
        ];
    }

    private function synchronizeSuccessfulPaymentFromGateway(
        FamilyPaymentTransaction $transaction,
        array $gatewayRecord,
        string $gatewayReason = ''
    ): void {
        if ($transaction->status === 'success' && $transaction->paid_at !== null) {
            return;
        }

        $billing = $transaction->familyBilling()->first();

        if (! $billing) {
            return;
        }

        $paidAmount = $this->normalizeGatewayAmount($gatewayRecord['billpaymentAmount'] ?? $transaction->amount);
        $invoiceNo = (string) ($gatewayRecord['billpaymentInvoiceNo'] ?? '');
        $paymentDate = (string) ($gatewayRecord['billPaymentDate'] ?? $gatewayRecord['billpaymentDate'] ?? '');

        DB::transaction(function () use ($transaction, $billing, $paidAmount, $invoiceNo, $paymentDate, $gatewayReason): void {
            $billing->refresh();

            $feeOutstanding = max(0, (float) $billing->fee_amount - (float) $billing->paid_amount);
            $feeOutstandingAtCheckout = max(0, (float) data_get($transaction->raw_return, 'outstanding_at_checkout', $feeOutstanding));
            $feeOutstanding = min($feeOutstanding, $feeOutstandingAtCheckout);
            $feePaid = min($feeOutstanding, $paidAmount);
            $donation = max(0, $paidAmount - $feePaid);

            $billing->paid_amount = min((float) $billing->fee_amount, (float) $billing->paid_amount + $feePaid);
            $billing->status = ((float) $billing->paid_amount >= (float) $billing->fee_amount) ? 'paid' : 'partial';
            $billing->save();

            $transaction->forceFill([
                'status' => 'success',
                'return_status' => 'successful',
                'provider_invoice_no' => filled($invoiceNo) ? $invoiceNo : $transaction->provider_invoice_no,
                'amount' => $paidAmount,
                'fee_amount_paid' => $feePaid,
                'donation_amount' => $donation,
                'paid_at' => now(),
                'status_reason' => filled($paymentDate)
                    ? "Paid at {$paymentDate}"
                    : ($gatewayReason !== '' ? $gatewayReason : $transaction->status_reason),
            ])->save();
        });

        $transaction->refresh();

        if (filled($transaction->payer_phone)) {
            $billing->registerPhone((string) $transaction->payer_phone);
        }

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
                Log::warning('Unable to send payment receipt WhatsApp notification from gateway sync.', [
                    'transaction_id' => $transaction->id,
                    'payer_phone' => $transaction->payer_phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        try {
            $this->paymentNotificationService->sendTeacherClassNotifications($transaction);
        } catch (\Throwable $exception) {
            Log::warning('Unable to send teacher WhatsApp notifications from gateway sync.', [
                'transaction_id' => $transaction->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function mapGatewayStatus(string $billPaymentStatus): string
    {
        return match (trim($billPaymentStatus)) {
            '1' => 'success',
            '3' => 'failed',
            '2', '4' => 'pending',
            default => 'pending',
        };
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

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'success' => 'Success',
            'failed' => 'Failed',
            'pending' => 'Pending',
            'not_started' => 'Not Started',
            default => ucfirst($status),
        };
    }
}