<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\LegacyStudentPayment;
use App\Models\Student;
use App\Models\User;
use App\Support\MalaysianPhone;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ClassProgressService
{
    public function __construct(
        private readonly PaymentReportingService $paymentReportingService,
        private readonly WhatsAppMessageQueueService $whatsAppMessageQueueService
    ) {
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function leaderboardRows(int $billingYear, User $viewer, bool $includeWhatsappMeta = false): Collection
    {
        $dataset = $this->buildDataset($billingYear);

        return $dataset['summary_rows']
            ->map(function (array $row) use ($viewer, $billingYear, $includeWhatsappMeta): array {
                $className = (string) $row['class_name'];
                $teacher = $row['teacher'];
                $isMyClass = $this->isOwnClassViewer($viewer, $className);
                $canViewFullDetails = $this->canViewClassDetails($viewer, $className);
                $canViewAnyDetails = $canViewFullDetails;

                $badges = [];
                if ($isMyClass) {
                    $badges[] = ['label' => 'Kelas Saya', 'classes' => 'border-emerald-200 bg-emerald-50 text-emerald-700'];
                }

                if ($includeWhatsappMeta) {
                    $normalizedPhone = $teacher?->phone ? MalaysianPhone::normalize((string) $teacher->phone) : null;
                    $recentlyQueued = $this->whatsAppMessageQueueService->hasRecentDuplicate($className, $billingYear);

                    if ($teacher === null) {
                        $badges[] = ['label' => 'Missing Teacher', 'classes' => 'border-rose-200 bg-rose-50 text-rose-700'];
                    } elseif ($normalizedPhone === null) {
                        $badges[] = ['label' => 'Missing Phone', 'classes' => 'border-amber-200 bg-amber-50 text-amber-700'];
                    } else {
                        $badges[] = ['label' => 'Ready', 'classes' => 'border-sky-200 bg-sky-50 text-sky-700'];
                    }

                    if ($recentlyQueued) {
                        $badges[] = ['label' => 'Recently Queued', 'classes' => 'border-sky-200 bg-sky-50 text-sky-700'];
                    }
                }

                return [
                    ...$row,
                    'teacher_name' => $teacher?->name ? (string) $teacher->name : '-',
                    'teacher_phone' => $includeWhatsappMeta ? ((string) (MalaysianPhone::normalize((string) ($teacher?->phone ?? '')) ?? '')) : '',
                    'is_my_class' => $isMyClass,
                    'can_view_full_details' => $canViewFullDetails,
                    'can_view_any_details' => $canViewAnyDetails,
                    'status_badges' => $badges,
                ];
            })
            ->sortBy([
                [fn (array $row): int => $row['is_my_class'] ? 0 : 1, 'asc'],
                ['completion_percent', 'desc'],
                ['jumlah_kutipan', 'desc'],
                ['class_name', 'asc'],
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function getClassSummary(string $className, int $billingYear): array
    {
        $dataset = $this->buildDataset($billingYear);
        $summary = $dataset['summary_lookup']->get(trim($className));

        if (! is_array($summary)) {
            throw new InvalidArgumentException('The selected class could not be found.');
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function getClassPaymentDetails(string $className, int $billingYear, User $viewer): array
    {
        $className = trim($className);
        $dataset = $this->buildDataset($billingYear);
        $summary = $dataset['summary_lookup']->get($className);

        if (! is_array($summary)) {
            throw new InvalidArgumentException('The selected class could not be found.');
        }

        $canViewFullDetails = $this->canViewClassDetails($viewer, $className);
        $canViewAnyDetails = $canViewFullDetails;

        return [
            'billing_year' => $billingYear,
            'class_name' => $className,
            'summary' => [
                'class_name' => $summary['class_name'],
                'teacher_name' => $summary['teacher']?->name ? (string) $summary['teacher']->name : '-',
                'completion_percent' => (float) $summary['completion_percent'],
                'total_families' => (int) $summary['total_families'],
                'fully_paid_families' => (int) $summary['fully_paid_families'],
                'partial_paid_families' => (int) $summary['partial_paid_families'],
                'unpaid_families' => (int) $summary['unpaid_families'],
                'jumlah_kutipan' => (float) $summary['jumlah_kutipan'],
                'baki_tertunggak' => (float) $summary['baki_tertunggak'],
                'is_my_class' => $this->isOwnClassViewer($viewer, $className),
            ],
            'can_view_full_details' => $canViewFullDetails,
            'summary_only' => ! $canViewFullDetails,
            'summary_only_message' => $canViewAnyDetails
                ? 'Maklumat terperinci dipaparkan dalam mod bacaan sahaja untuk peranan ini.'
                : 'Maklumat terperinci tidak tersedia untuk peranan ini.',
            'paid_entries' => $canViewFullDetails ? $this->getPaidStudents($className, $billingYear, $viewer)->all() : [],
            'unpaid_entries' => $canViewFullDetails ? $this->getUnpaidStudents($className, $billingYear, $viewer)->all() : [],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getPaidStudents(string $className, int $billingYear, User $viewer): Collection
    {
        $className = trim($className);
        $dataset = $this->buildDataset($billingYear);

        if (! $this->canViewClassDetails($viewer, $className)) {
            return collect();
        }

        return $this->classFamilyEntries($dataset, $className)
            ->filter(fn (array $entry): bool => in_array((string) $entry['status_key'], ['paid', 'partial'], true))
            ->sortByDesc(fn (array $entry): int => $entry['latest_payment_at']?->getTimestamp() ?? 0)
            ->values()
            ->map(fn (array $entry): array => [
                'student_names' => $entry['student_names'],
                'student_name_display' => implode(', ', $entry['student_names']),
                'family_code' => $entry['family_code'],
                'paid_amount' => (float) $entry['paid_amount'],
                'donation_total' => (float) $entry['donation_total'],
                'latest_payment_at' => $entry['latest_payment_at']?->format('d M Y'),
                'status_key' => $entry['status_key'],
                'status_label' => $entry['status_key'] === 'partial' ? 'Sebahagian' : 'Selesai',
                'is_partial' => (bool) $entry['is_partial'],
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getUnpaidStudents(string $className, int $billingYear, User $viewer): Collection
    {
        $className = trim($className);
        $dataset = $this->buildDataset($billingYear);

        if (! $this->canViewClassDetails($viewer, $className)) {
            return collect();
        }

        $showPhone = $this->canViewContactPhone($viewer, $className);

        return $this->classFamilyEntries($dataset, $className)
            ->filter(fn (array $entry): bool => in_array((string) $entry['status_key'], ['not_started', 'pending'], true))
            ->sortBy([
                [fn (array $entry): int => $entry['previous_year_paid'] ? 0 : 1, 'asc'],
                [fn (array $entry): string => implode(', ', $entry['student_names']), 'asc'],
            ])
            ->values()
            ->map(fn (array $entry): array => [
                'student_names' => $entry['student_names'],
                'student_name_display' => implode(', ', $entry['student_names']),
                'family_code' => $entry['family_code'],
                'parent_name' => $entry['parent_name'],
                'parent_phone' => $showPhone ? $entry['parent_phone'] : null,
                'has_previous_year_payment' => (bool) $entry['previous_year_paid'],
                'previous_year_paid' => (bool) $entry['previous_year_paid'],
                'previous_paid_year' => $entry['previous_paid_year'],
                'previous_paid_year_short' => $this->getPreviousPaidYearBadge($entry['previous_paid_year']),
                'previous_year_badge' => $this->getPreviousPaidYearBadge($entry['previous_paid_year']),
                'previous_year_tooltip' => $entry['previous_paid_year'] !== null
                    ? sprintf('Bayar tahun %d', (int) $entry['previous_paid_year'])
                    : null,
            ]);
    }

    public function hasPaidPreviousYear(string $familyCode, int $previousYear): bool
    {
        return $this->mostRecentPreviousPaidYearByFamily($previousYear + 1)->get($familyCode) === $previousYear;
    }

    public function getPreviousPaidYearBadge(?int $year): ?string
    {
        if ($year === null || $year < 1000) {
            return null;
        }

        return substr((string) $year, -2);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDataset(int $billingYear): array
    {
        $classFamilyMetrics = $this->paymentReportingService->familyMetricsByClass($billingYear);

        if ($classFamilyMetrics->isEmpty()) {
            return [
                'summary_rows' => collect(),
                'summary_lookup' => collect(),
                'families' => collect(),
            ];
        }

        $previousPaidYearByFamily = $this->mostRecentPreviousPaidYearByFamily($billingYear);

        $billings = FamilyBilling::query()
            ->with([
                'paymentTransactions' => fn ($query) => $query->where('status', 'success')->orderByDesc('paid_at'),
            ])
            ->where('billing_year', $billingYear)
            ->whereIn('family_code', $classFamilyMetrics->pluck('family_code')->unique()->all())
            ->get()
            ->keyBy('family_code');

        $families = $classFamilyMetrics
            ->map(function (array $metric) use ($billings, $previousPaidYearByFamily): array {
                $familyCode = (string) $metric['family_code'];
                /** @var FamilyBilling|null $billing */
                $billing = $billings->get($familyCode);
                $latestTransaction = $billing?->paymentTransactions?->first();
                $previousPaidYear = $previousPaidYearByFamily->get($familyCode);

                return [
                    ...$metric,
                    'latest_payment_at' => $latestTransaction?->paid_at_for_display,
                    'previous_year_paid' => $previousPaidYear !== null,
                    'previous_paid_year' => $previousPaidYear !== null ? (int) $previousPaidYear : null,
                ];
            })
            ->values();

        $teachersByClass = User::query()
            ->withAnyRole(['teacher', 'super_teacher'])
            ->whereIn('class_name', $families->pluck('class_name')->unique()->all())
            ->orderByRaw("case when role = 'teacher' then 0 else 1 end")
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'class_name', 'role', 'is_active'])
            ->groupBy(fn (User $teacher): string => trim((string) $teacher->class_name))
            ->map(fn (Collection $rows): ?User => $rows->first())
            ->filter();

        $summaryRows = $families
            ->groupBy('class_name')
            ->map(function (Collection $rows, string $className) use ($billingYear, $teachersByClass): array {
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
                    'teacher' => $teachersByClass->get($className),
                    'year_level' => $this->extractYearLevel($className),
                ];
            })
            ->sortBy([
                ['completion_percent', 'desc'],
                ['jumlah_kutipan', 'desc'],
                ['class_name', 'asc'],
            ])
            ->values();

        return [
            'summary_rows' => $summaryRows,
            'summary_lookup' => $summaryRows->keyBy('class_name'),
            'families' => $families,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function classFamilyEntries(array $dataset, string $className): Collection
    {
        return collect($dataset['families'])
            ->where('class_name', trim($className))
            ->values();
    }

    private function extractYearLevel(string $className): ?int
    {
        if (preg_match('/^(\d+)/', trim($className), $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);

        return ($year >= 1 && $year <= 6) ? $year : null;
    }

    private function isOwnClassViewer(User $viewer, string $className): bool
    {
        $viewerClass = trim((string) ($viewer->class_name ?? ''));

        return $viewerClass !== '' && mb_strtoupper($viewerClass) === mb_strtoupper(trim($className));
    }

    private function canViewClassDetails(User $viewer, string $className): bool
    {
        if ($viewer->isSystemAdmin()) {
            return true;
        }

        if ($viewer->hasAnyRole(['teacher', 'super_teacher'])) {
            return true;
        }

        return false;
    }

    private function canViewContactPhone(User $viewer, string $className): bool
    {
        if ($viewer->isSystemAdmin()) {
            return true;
        }

        if ($viewer->hasAnyRole(['teacher', 'super_teacher'])) {
            return $this->isOwnClassViewer($viewer, $className);
        }

        return false;
    }

    /**
     * @return Collection<string, int>
     */
    private function mostRecentPreviousPaidYearByFamily(int $billingYear): Collection
    {
        $paidFamilyBillingYears = FamilyBilling::query()
            ->with(['paymentPlan.installments'])
            ->where('billing_year', '<', $billingYear)
            ->orderByDesc('billing_year')
            ->get()
            ->reduce(function (Collection $carry, FamilyBilling $billing): Collection {
                $familyCode = (string) $billing->family_code;

                if ($carry->has($familyCode)) {
                    return $carry;
                }

                $metric = $this->paymentReportingService->familyMetric($billing);
                if ((bool) ($metric['is_fully_paid'] ?? false)) {
                    $carry->put($familyCode, (int) $billing->billing_year);
                }

                return $carry;
            }, collect());

        $legacyPaidYears = LegacyStudentPayment::query()
            ->where('source_year', '<', $billingYear)
            ->where('payment_status', 'paid')
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->orderByDesc('source_year')
            ->get(['family_code', 'source_year'])
            ->reduce(function (Collection $carry, LegacyStudentPayment $payment): Collection {
                $familyCode = trim((string) $payment->family_code);

                if ($familyCode === '' || $carry->has($familyCode)) {
                    return $carry;
                }

                $carry->put($familyCode, (int) $payment->source_year);

                return $carry;
            }, collect());

        return $paidFamilyBillingYears
            ->merge($legacyPaidYears)
            ->keys()
            ->mapWithKeys(function (string $familyCode) use ($paidFamilyBillingYears, $legacyPaidYears): array {
                return [
                    $familyCode => max(
                        (int) ($paidFamilyBillingYears->get($familyCode) ?? 0),
                        (int) ($legacyPaidYears->get($familyCode) ?? 0),
                    ),
                ];
            });
    }
}
