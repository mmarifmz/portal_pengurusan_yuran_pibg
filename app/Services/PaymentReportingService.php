<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
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
     * @return Collection<int, array<string, mixed>>
     */
    public function classLeaderboard(int $billingYear): Collection
    {
        $familyMetrics = $this->familyMetricsForYear($billingYear)->keyBy('family_code');

        if ($familyMetrics->isEmpty()) {
            return collect();
        }

        $dominantClassByFamily = Student::query()
            ->active()
            ->where('billing_year', $billingYear)
            ->whereIn('family_code', $familyMetrics->keys()->all())
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->get(['family_code', 'class_name'])
            ->groupBy(fn (Student $student): string => (string) $student->family_code)
            ->map(function (Collection $familyStudents): string {
                return (string) ($familyStudents
                    ->pluck('class_name')
                    ->map(fn ($className): string => trim((string) $className))
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first() ?? 'Unassigned');
            });

        return $familyMetrics
            ->map(function (array $metric, string $familyCode) use ($dominantClassByFamily, $billingYear): array {
                $metric['class_name'] = (string) ($dominantClassByFamily->get($familyCode) ?? 'Unassigned');
                $metric['billing_year'] = $billingYear;

                return $metric;
            })
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
}
