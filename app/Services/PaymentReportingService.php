<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PaymentReportingService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function familyMetricsForYear(int $billingYear): Collection
    {
        $activeFamilyCodes = Student::activeFamilyCodesForYear($billingYear);

        if ($activeFamilyCodes->isEmpty()) {
            return collect();
        }

        $billings = FamilyBilling::query()
            ->with(['paymentPlan.installments', 'socialTags'])
            ->where('billing_year', $billingYear)
            ->whereIn('family_code', $activeFamilyCodes->all())
            ->get();

        if ($billings->isEmpty()) {
            return collect();
        }

        $donationByBillingId = FamilyPaymentTransaction::query()
            ->selectRaw('family_billing_id, SUM(COALESCE(donation_amount, 0)) as donation_total')
            ->where('status', 'success')
            ->whereIn('family_billing_id', $billings->pluck('id')->all())
            ->groupBy('family_billing_id')
            ->pluck('donation_total', 'family_billing_id');

        return $billings->map(function (FamilyBilling $billing) use ($donationByBillingId): array {
            return $this->familyMetric(
                $billing,
                (float) ($donationByBillingId->get($billing->id) ?? 0)
            );
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function familyMetricsByClass(int $billingYear): Collection
    {
        $familyMetrics = $this->familyMetricsForYear($billingYear)->keyBy('family_code');

        if ($familyMetrics->isEmpty()) {
            return collect();
        }

        $studentsByFamily = Student::query()
            ->active()
            ->where('billing_year', $billingYear)
            ->whereIn('family_code', $familyMetrics->keys()->all())
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->orderBy('full_name')
            ->get(['family_code', 'class_name', 'full_name', 'parent_name', 'parent_phone'])
            ->groupBy(fn (Student $student): string => (string) $student->family_code);

        return $familyMetrics
            ->flatMap(function (array $metric, string $familyCode) use ($studentsByFamily, $billingYear): array {
                /** @var Collection<int, Student> $familyStudents */
                $familyStudents = $studentsByFamily->get($familyCode, collect());
                $familyStudentNames = $familyStudents
                    ->pluck('full_name')
                    ->filter()
                    ->map(fn ($name): string => trim((string) $name))
                    ->filter()
                    ->values()
                    ->all();

                if ($familyStudents->isEmpty()) {
                    return [];
                }

                return $familyStudents
                    ->groupBy(fn (Student $student): string => trim((string) $student->class_name))
                    ->filter(fn (Collection $classStudents, string $className): bool => $className !== '')
                    ->map(function (Collection $classStudents, string $className) use ($metric, $familyCode, $billingYear, $familyStudentNames): array {
                        /** @var Student|null $primaryStudent */
                        $primaryStudent = $classStudents->first();

                        return [
                            ...$metric,
                            'family_code' => $familyCode,
                            'billing_year' => $billingYear,
                            'class_name' => $className,
                            'student_names' => $classStudents
                                ->pluck('full_name')
                                ->filter()
                                ->map(fn ($name): string => trim((string) $name))
                                ->filter()
                                ->values()
                                ->all(),
                            'family_student_names' => $familyStudentNames,
                            'parent_name' => $primaryStudent?->parent_name ? (string) $primaryStudent->parent_name : '',
                            'parent_phone' => $primaryStudent?->parent_phone ? (string) $primaryStudent->parent_phone : '',
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function familyMetric(FamilyBilling $billing, float $donationTotal = 0): array
    {
        $plan = $billing->relationLoaded('paymentPlan')
            ? $billing->paymentPlan
            : $billing->paymentPlan()->with('installments')->first();

        $feeAmount = round((float) $billing->fee_amount, 2);
        $paidAmount = round((float) ($plan?->paid_amount ?? $billing->paid_amount), 2);
        $balanceAmount = round(max(0, (float) ($plan?->balance_amount ?? $billing->outstanding_amount)), 2);
        $installmentCount = $plan?->installments?->count() ?? 0;
        $paidInstallments = $plan?->installments?->where('status', FamilyPaymentInstallment::STATUS_PAID)->count() ?? 0;
        $hasPaidInstallment = $paidInstallments > 0;
        $hasSuccessfulPlanPayment = $plan
            ? FamilyPaymentTransaction::query()
                ->where('family_billing_id', $billing->id)
                ->where('status', 'success')
                ->whereHas('installment', fn ($query) => $query->where('family_payment_plan_id', $plan->id))
                ->exists()
            : false;

        if (! $plan && $paidAmount <= 0) {
            $statusKey = 'not_started';
        } elseif ($paidAmount <= 0 && ! $hasPaidInstallment && ! $hasSuccessfulPlanPayment) {
            $statusKey = 'pending';
        } elseif ($paidAmount >= $feeAmount && $feeAmount > 0) {
            $statusKey = 'paid';
        } elseif ($paidAmount > 0 && $paidAmount < $feeAmount) {
            $statusKey = 'partial';
        } else {
            $statusKey = 'pending';
        }

        $statusLabel = match ($statusKey) {
            'not_started' => 'Belum Mula',
            'partial' => 'Bayaran Sebahagian',
            'paid' => 'Selesai Dibayar',
            default => 'Belum Dibayar',
        };

        $planLabel = match ((string) ($plan?->plan_type ?? FamilyPaymentPlan::PLAN_FULL)) {
            FamilyPaymentPlan::PLAN_TWO_TIMES => 'Ansuran 2 Kali',
            FamilyPaymentPlan::PLAN_THREE_TIMES => 'Ansuran 3 Kali',
            default => 'Penuh',
        };

        return [
            'family_billing_id' => $billing->id,
            'family_code' => (string) $billing->family_code,
            'plan_type' => (string) ($plan?->plan_type ?? FamilyPaymentPlan::PLAN_FULL),
            'plan_label' => $planLabel,
            'has_plan' => $plan !== null,
            'fee_amount' => $feeAmount,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balanceAmount,
            'paid_installments' => $paidInstallments,
            'installment_count' => $installmentCount,
            'paid_installments_summary' => $plan
                ? sprintf('%d/%d', $paidInstallments, max(1, $installmentCount))
                : ($statusKey === 'paid' ? '1/1' : ($statusKey === 'partial' ? 'Bayaran Sebahagian' : '-')),
            'status_key' => $statusKey,
            'status_label' => $statusLabel,
            'is_fully_paid' => $statusKey === 'paid',
            'is_partial' => $statusKey === 'partial',
            'donation_total' => round(max(0, $donationTotal), 2),
            'total_collection' => round($paidAmount + max(0, $donationTotal), 2),
            'social_tags' => $billing->relationLoaded('socialTags')
                ? $billing->socialTags->pluck('name')->filter()->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, float|int>
     */
    public function dashboardStats(int $billingYear): array
    {
        $metrics = $this->familyMetricsForYear($billingYear);

        $totalFamilies = $metrics->count();
        $fullyPaidFamilies = $metrics->where('status_key', 'paid')->count();
        $partialFamilies = $metrics->where('status_key', 'partial')->count();
        $unpaidFamilies = $metrics->whereIn('status_key', ['not_started', 'pending'])->count();
        $totalYuranCollected = round((float) $metrics->sum('paid_amount'), 2);
        $totalOutstanding = round((float) $metrics->sum('balance_amount'), 2);
        $totalSumbanganTambahanCollected = round((float) $metrics->sum('donation_total'), 2);
        $grandTotalCollected = round($totalYuranCollected + $totalSumbanganTambahanCollected, 2);

        return [
            'total_families' => $totalFamilies,
            'fully_paid_families' => $fullyPaidFamilies,
            'partial_paid_families' => $partialFamilies,
            'unpaid_families' => $unpaidFamilies,
            'total_yuran_collected' => $totalYuranCollected,
            'total_outstanding_balance' => $totalOutstanding,
            'total_sumbangan_tambahan_collected' => $totalSumbanganTambahanCollected,
            'grand_total_collected' => $grandTotalCollected,
            'completion_percentage' => $totalFamilies > 0 ? round(($fullyPaidFamilies / $totalFamilies) * 100, 2) : 0.0,
        ];
    }

    /**
     * Compare families who fully paid in the previous year with their current-year status.
     *
     * @return array<string, float|int>
     */
    public function previousYearPayerCohort(int $currentYear): array
    {
        $previousYear = $currentYear - 1;

        $previousPortalPayers = FamilyBilling::query()
            ->where('billing_year', $previousYear)
            ->where('fee_amount', '>', 0)
            ->whereColumn('paid_amount', '>=', 'fee_amount')
            ->pluck('family_code');

        $previousLegacyPayers = LegacyStudentPayment::query()
            ->where('source_year', $previousYear)
            ->where('payment_status', 'paid')
            ->pluck('family_code');

        $previousPayerCodes = $previousPortalPayers
            ->merge($previousLegacyPayers)
            ->map(fn ($familyCode): string => trim((string) $familyCode))
            ->filter()
            ->unique()
            ->values();

        $activeCurrentYearCodes = Student::activeFamilyCodesForYear($currentYear);
        $activeCohortCodes = $previousPayerCodes
            ->intersect($activeCurrentYearCodes)
            ->values();

        $currentMetrics = FamilyBilling::query()
            ->with(['paymentPlan.installments', 'socialTags'])
            ->where('billing_year', $currentYear)
            ->whereIn('family_code', $activeCohortCodes->all())
            ->get()
            ->map(fn (FamilyBilling $billing): array => $this->familyMetric($billing))
            ->keyBy('family_code');

        $fullyPaidFamilies = $currentMetrics->where('status_key', 'paid')->count();
        $partialPaidFamilies = $currentMetrics->where('status_key', 'partial')->count();
        $unpaidFamilies = $currentMetrics->whereIn('status_key', ['not_started', 'pending'])->count();
        $missingBillingFamilies = max(0, $activeCohortCodes->count() - $currentMetrics->count());
        $familiesWithCurrentBilling = $currentMetrics->count();

        return [
            'previous_year' => $previousYear,
            'current_year' => $currentYear,
            'previous_paid_families' => $previousPayerCodes->count(),
            'active_current_year_families' => $activeCohortCodes->count(),
            'inactive_or_departed_families' => max(0, $previousPayerCodes->count() - $activeCohortCodes->count()),
            'families_with_current_billing' => $familiesWithCurrentBilling,
            'fully_paid_families' => $fullyPaidFamilies,
            'partial_paid_families' => $partialPaidFamilies,
            'unpaid_families' => $unpaidFamilies,
            'missing_billing_families' => $missingBillingFamilies,
            'unpaid_percentage' => $familiesWithCurrentBilling > 0
                ? round(($unpaidFamilies / $familiesWithCurrentBilling) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Compare cumulative collections at equal elapsed campaign windows.
     *
     * Day 1 is the calendar date of the earliest successful payment in each year.
     *
     * @return array<string, mixed>
     */
    public function collectionWindowComparison(int $currentYear): array
    {
        $previousYear = $currentYear - 1;
        $windowDays = [30, 60, 90];
        $previousSeries = $this->collectionSeriesForYear($previousYear, $windowDays);
        $currentSeries = $this->collectionSeriesForYear($currentYear, $windowDays);

        $windows = collect($windowDays)
            ->map(function (int $days) use ($previousSeries, $currentSeries): array {
                $previousAmount = (float) ($previousSeries['window_amounts'][$days] ?? 0);
                $currentAmount = (float) ($currentSeries['window_amounts'][$days] ?? 0);
                $difference = round($currentAmount - $previousAmount, 2);

                return [
                    'days' => $days,
                    'label' => $days.' hari',
                    'previous_amount' => round($previousAmount, 2),
                    'current_amount' => round($currentAmount, 2),
                    'difference' => $difference,
                    'difference_percentage' => $previousAmount > 0
                        ? round(($difference / $previousAmount) * 100, 1)
                        : null,
                    'previous_complete' => (bool) ($previousSeries['window_complete'][$days] ?? false),
                    'current_complete' => (bool) ($currentSeries['window_complete'][$days] ?? false),
                ];
            })
            ->values()
            ->all();

        return [
            'previous_year' => $previousSeries,
            'current_year' => $currentSeries,
            'windows' => $windows,
            'all_windows_complete' => collect($windows)->every(
                fn (array $window): bool => $window['previous_complete'] && $window['current_complete']
            ),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function classLeaderboard(int $billingYear): Collection
    {
        $classFamilyMetrics = $this->familyMetricsByClass($billingYear);

        if ($classFamilyMetrics->isEmpty()) {
            return collect();
        }

        return $classFamilyMetrics
            ->groupBy('class_name')
            ->map(function (Collection $rows, string $className) use ($billingYear): array {
                $totalFamilies = $rows->count();
                $fullyPaid = $rows->where('status_key', 'paid')->count();
                $partialPaid = $rows->where('status_key', 'partial')->count();
                $unpaid = $rows->whereIn('status_key', ['not_started', 'pending'])->count();
                $yuranCollected = round((float) $rows->sum('paid_amount'), 2);
                $donationCollected = round((float) $rows->sum('donation_total'), 2);
                $outstanding = round((float) $rows->sum('balance_amount'), 2);

                return [
                    'class_name' => $className,
                    'billing_year' => $billingYear,
                    'total_families' => $totalFamilies,
                    'fully_paid_families' => $fullyPaid,
                    'partial_paid_families' => $partialPaid,
                    'unpaid_families' => $unpaid,
                    'yuran_collected' => $yuranCollected,
                    'sumbangan_tambahan_collected' => $donationCollected,
                    'jumlah_kutipan' => round($yuranCollected + $donationCollected, 2),
                    'baki_tertunggak' => $outstanding,
                    'completion_percent' => $totalFamilies > 0 ? round(($fullyPaid / $totalFamilies) * 100, 2) : 0.0,
                ];
            })
            ->sortBy([
                ['completion_percent', 'desc'],
                ['jumlah_kutipan', 'desc'],
                ['class_name', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  array<int, int>  $windowDays
     * @return array<string, mixed>
     */
    private function collectionSeriesForYear(int $year, array $windowDays): array
    {
        $timezone = (string) config('app.timezone', 'Asia/Kuala_Lumpur');
        $legacyPayments = LegacyStudentPayment::query()
            ->where('source_year', $year)
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->get(['family_code', 'payment_reference', 'paid_at', 'amount_paid']);

        if ($legacyPayments->isNotEmpty()) {
            $rows = $legacyPayments
                ->groupBy(function (LegacyStudentPayment $payment): string {
                    $reference = trim((string) $payment->payment_reference);
                    if ($reference !== '') {
                        return 'REF:'.$reference;
                    }

                    return sprintf(
                        'FAM:%s|PAID:%s|PAIDAMT:%0.2f',
                        (string) $payment->family_code,
                        optional($payment->paid_at)->format('Y-m-d H:i:s') ?? '-',
                        (float) $payment->amount_paid
                    );
                })
                ->map(function (Collection $group) use ($timezone): array {
                    /** @var LegacyStudentPayment|null $first */
                    $first = $group->first();
                    $paidAt = $group
                        ->pluck('paid_at')
                        ->filter()
                        ->sort()
                        ->first() ?? $first?->paid_at;

                    return [
                        'paid_at' => Carbon::parse($paidAt, $timezone)->timezone($timezone),
                        'amount' => (float) $group->max('amount_paid'),
                    ];
                })
                ->values();
            $source = 'legacy';
        } else {
            $rows = FamilyPaymentTransaction::query()
                ->where('status', 'success')
                ->whereNotNull('paid_at')
                ->whereYear('paid_at', $year)
                ->orderBy('paid_at')
                ->get(['amount', 'paid_at'])
                ->map(fn (FamilyPaymentTransaction $transaction): array => [
                    'paid_at' => Carbon::parse($transaction->paid_at, $timezone)->timezone($timezone),
                    'amount' => (float) $transaction->amount,
                ])
                ->values();
            $source = 'portal';
        }

        if ($rows->isEmpty()) {
            return [
                'year' => $year,
                'source' => $source,
                'start_date' => null,
                'start_date_label' => 'Tiada data',
                'transaction_count' => 0,
                'window_amounts' => collect($windowDays)->mapWithKeys(fn (int $days): array => [$days => 0.0])->all(),
                'window_complete' => collect($windowDays)->mapWithKeys(fn (int $days): array => [$days => false])->all(),
            ];
        }

        /** @var Carbon $startDate */
        $startDate = $rows
            ->pluck('paid_at')
            ->sort()
            ->first()
            ->copy()
            ->startOfDay();
        $today = now($timezone)->endOfDay();
        $windowAmounts = [];
        $windowComplete = [];

        foreach ($windowDays as $days) {
            $windowEnd = $startDate->copy()->addDays($days - 1)->endOfDay();
            $windowAmounts[$days] = round((float) $rows
                ->filter(fn (array $row): bool => $row['paid_at']->lessThanOrEqualTo($windowEnd))
                ->sum('amount'), 2);
            $windowComplete[$days] = $today->greaterThanOrEqualTo($windowEnd);
        }

        return [
            'year' => $year,
            'source' => $source,
            'start_date' => $startDate->toDateString(),
            'start_date_label' => $startDate->format('d M Y'),
            'transaction_count' => $rows->count(),
            'window_amounts' => $windowAmounts,
            'window_complete' => $windowComplete,
        ];
    }
}
