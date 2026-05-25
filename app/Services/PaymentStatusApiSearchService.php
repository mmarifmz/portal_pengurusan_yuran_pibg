<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Support\ParentPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PaymentStatusApiSearchService
{
    public function __construct(private readonly PaymentReportingService $paymentReportingService)
    {
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $keyword, int $billingYear, ?string $className = null): Collection
    {
        $keyword = trim($keyword);
        $className = trim((string) $className);

        if ($keyword === '') {
            return collect();
        }

        $familyCodes = $this->matchingFamilyCodes($keyword, $billingYear, $className);

        if ($familyCodes->isEmpty()) {
            return collect();
        }

        $billings = FamilyBilling::query()
            ->with([
                'paymentPlan.installments',
                'students' => function ($query) use ($billingYear, $className): void {
                    $query
                        ->where('billing_year', $billingYear)
                        ->when($className !== '', fn ($builder) => $this->applyClassFilter($builder, $className))
                        ->orderBy('full_name');
                },
            ])
            ->where('billing_year', $billingYear)
            ->whereIn('family_code', $familyCodes->all())
            ->when($className !== '', function (Builder $query) use ($billingYear, $className): void {
                $query->whereHas('students', function (Builder $studentQuery) use ($billingYear, $className): void {
                    $studentQuery->where('billing_year', $billingYear);
                    $this->applyClassFilter($studentQuery, $className);
                });
            })
            ->orderBy('family_code')
            ->limit(25)
            ->get();

        if ($billings->isEmpty()) {
            return collect();
        }

        $billingIds = $billings->pluck('id')->all();

        $donationByBillingId = FamilyPaymentTransaction::query()
            ->selectRaw('family_billing_id, SUM(COALESCE(donation_amount, 0)) as donation_total')
            ->where('status', 'success')
            ->whereIn('family_billing_id', $billingIds)
            ->groupBy('family_billing_id')
            ->pluck('donation_total', 'family_billing_id');

        $latestPaymentByBillingId = FamilyPaymentTransaction::query()
            ->where('status', 'success')
            ->whereIn('family_billing_id', $billingIds)
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('family_billing_id')
            ->map(fn (Collection $payments) => $payments->first());

        return $billings
            ->map(function (FamilyBilling $billing) use ($donationByBillingId, $latestPaymentByBillingId): array {
                $metric = $this->paymentReportingService->familyMetric(
                    $billing,
                    (float) ($donationByBillingId->get($billing->id) ?? 0)
                );

                /** @var FamilyPaymentTransaction|null $latestPayment */
                $latestPayment = $latestPaymentByBillingId->get($billing->id);

                $students = $billing->students
                    ->map(fn (Student $student): array => [
                        'name' => (string) $student->full_name,
                        'class' => (string) ($student->class_name ?: '-'),
                        'status' => $student->statusLabel(),
                    ])
                    ->values()
                    ->all();

                return [
                    'family_code' => (string) $billing->family_code,
                    'bill_year' => (int) $billing->billing_year,
                    'guardian_name' => $this->guardianName($billing->students),
                    'students' => $students,
                    'payment' => [
                        'status' => $this->apiStatus((string) $metric['status_key']),
                        'status_label' => $this->apiStatusLabel((string) $metric['status_key']),
                        'total_due' => number_format((float) $metric['fee_amount'], 2, '.', ''),
                        'total_paid' => number_format((float) $metric['paid_amount'], 2, '.', ''),
                        'outstanding' => number_format((float) $metric['balance_amount'], 2, '.', ''),
                        'latest_payment_date' => $latestPayment?->paid_at?->toDateString(),
                        'receipt_url' => $latestPayment?->receipt_uuid ? route('receipts.show', $latestPayment->receipt_uuid) : null,
                        'remarks' => $this->remarks($billing->students, (string) $metric['status_key'], (float) $metric['balance_amount']),
                    ],
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function matchingFamilyCodes(string $keyword, int $billingYear, string $className): Collection
    {
        $like = '%'.mb_strtolower($keyword).'%';
        $phoneSearch = ParentPhone::normalizeForMatch($keyword);

        $studentCodes = Student::query()
            ->where('billing_year', $billingYear)
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->when($className !== '', fn ($query) => $this->applyClassFilter($query, $className))
            ->where(function (Builder $query) use ($like): void {
                $query
                    ->whereRaw('LOWER(full_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(parent_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(family_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(parent_phone) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(parent_email) LIKE ?', [$like]);
            })
            ->pluck('family_code');

        $billingCodes = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->whereRaw('LOWER(family_code) LIKE ?', [$like])
            ->pluck('family_code');

        $registeredPhoneCodes = collect();
        if ($phoneSearch !== '') {
            $registeredPhoneCodes = FamilyBilling::query()
                ->where('billing_year', $billingYear)
                ->whereHas('phones', fn (Builder $query) => $query->where('normalized_phone', 'like', '%'.$phoneSearch.'%'))
                ->pluck('family_code');
        }

        return $studentCodes
            ->merge($billingCodes)
            ->merge($registeredPhoneCodes)
            ->map(fn ($familyCode): string => trim((string) $familyCode))
            ->filter()
            ->unique()
            ->values();
    }

    private function applyClassFilter(Builder $query, string $className): Builder
    {
        return $query->whereRaw('LOWER(class_name) LIKE ?', ['%'.mb_strtolower($className).'%']);
    }

    /**
     * @param Collection<int, Student> $students
     */
    private function guardianName(Collection $students): string
    {
        return (string) ($students
            ->pluck('parent_name')
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->first() ?? '');
    }

    private function apiStatus(string $statusKey): string
    {
        return match ($statusKey) {
            'paid' => 'Paid',
            'partial' => 'Partial',
            default => 'Unpaid',
        };
    }

    private function apiStatusLabel(string $statusKey): string
    {
        return match ($statusKey) {
            'paid' => 'Telah Bayar',
            'partial' => 'Bayaran Sebahagian',
            default => 'Belum Bayar',
        };
    }

    /**
     * @param Collection<int, Student> $students
     */
    private function remarks(Collection $students, string $statusKey, float $balanceAmount): ?string
    {
        if ($students->contains(fn (Student $student): bool => $student->isTransferred())) {
            return 'Ada murid telah berpindah / tidak aktif.';
        }

        if ($statusKey === 'partial') {
            return 'Bayaran sebahagian. Baki RM '.number_format($balanceAmount, 2, '.', '');
        }

        if (in_array($statusKey, ['not_started', 'pending'], true)) {
            return 'Belum ada bayaran penuh direkodkan.';
        }

        return null;
    }
}
