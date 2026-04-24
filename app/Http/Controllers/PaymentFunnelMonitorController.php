<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PaymentFunnelMonitorController extends Controller
{
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
                        || str_contains(mb_strtolower((string) $row['gateway_reason']), $needle);
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
