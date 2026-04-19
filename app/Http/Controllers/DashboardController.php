<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\LegacyStudentPayment;
use App\Models\FamilyPaymentTransaction;
use App\Models\SchoolCalendarEvent;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $role = $user?->isParent() ? 'parent' : 'staff';
        $billingYear = now()->year;

        $yearOptions = collect()
            ->merge(FamilyBilling::query()->distinct()->pluck('billing_year'))
            ->merge(LegacyStudentPayment::query()->distinct()->pluck('source_year'))
            ->merge([now()->year])
            ->filter(fn ($year) => is_numeric($year))
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values();

        if ($yearOptions->isEmpty()) {
            $yearOptions = collect([now()->year]);
        }

        $selectedDashboardYear = (int) $request->integer('dashboard_year', (int) $yearOptions->first());
        if (! $yearOptions->contains($selectedDashboardYear)) {
            $selectedDashboardYear = (int) $yearOptions->first();
        }
        $selectedClassYearFilter = trim((string) $request->query('class_tahun', 'all'));

        $familyBillings = FamilyBilling::with('students')
            ->where('billing_year', $selectedDashboardYear)
            ->orderBy('family_code')
            ->get();

        $students = Student::where('billing_year', $selectedDashboardYear)
            ->orderBy('family_code')
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get();

        $legacyPayments = LegacyStudentPayment::query()
            ->where('source_year', $selectedDashboardYear)
            ->where('payment_status', 'paid')
            ->get();

        $useLegacyKpiSource = $familyBillings->isEmpty() && $legacyPayments->isNotEmpty();
        $legacyPaymentTransactions = collect();
        $selectedYearTransactions = collect();
        if ($legacyPayments->isNotEmpty()) {
            $legacyPaymentTransactions = $legacyPayments
                ->groupBy(function (LegacyStudentPayment $payment): string {
                    $reference = trim((string) $payment->payment_reference);
                    if ($reference !== '') {
                        return $reference;
                    }

                    return sprintf(
                        'FAM:%s|PAID:%s|PAIDAMT:%0.2f',
                        (string) $payment->family_code,
                        optional($payment->paid_at)->format('Y-m-d H:i:s') ?? '-',
                        (float) $payment->amount_paid
                    );
                })
                ->map(function ($group) {
                    /** @var \Illuminate\Support\Collection<int, \App\Models\LegacyStudentPayment> $group */
                    $first = $group->first();
                    $className = (string) ($group->pluck('class_name')
                        ->filter()
                        ->countBy()
                        ->sortDesc()
                        ->keys()
                        ->first() ?? ($first?->class_name ?: 'Unassigned'));
                    return [
                        'paid_at' => $group->pluck('paid_at')->filter()->sort()->first() ?? $first?->paid_at,
                        'amount_paid' => (float) $group->max('amount_paid'),
                        'amount_due' => (float) $group->max('amount_due'),
                        'donation_amount' => (float) $group->max('donation_amount'),
                        'family_code' => (string) ($first?->family_code ?? ''),
                        'class_name' => $className !== '' ? $className : 'Unassigned',
                    ];
                })
                ->values();
        }
        if (! $useLegacyKpiSource) {
            $selectedYearTransactions = FamilyPaymentTransaction::query()
                ->with('familyBilling:id,family_code')
                ->where('status', 'success')
                ->whereYear('paid_at', $selectedDashboardYear)
                ->whereNotNull('paid_at')
                ->get();
        }

        $yuranThreshold = 100.0;

        $totalFamilies = $familyBillings->count();
        $totalStudents = $students->count();
        $familiesPaid = $familyBillings->filter(fn (FamilyBilling $billing) => $billing->outstanding_amount <= 0)->count();
        $tuitionCollected = $useLegacyKpiSource
            ? (float) $legacyPaymentTransactions->sum(fn (array $payment): float => min((float) ($payment['amount_paid'] ?? 0), $yuranThreshold))
            : (float) $selectedYearTransactions->sum(fn (FamilyPaymentTransaction $transaction): float => min((float) $transaction->amount, $yuranThreshold));
        $donationCollected = $useLegacyKpiSource
            ? (float) $legacyPaymentTransactions->sum(fn (array $payment): float => max(0, (float) ($payment['amount_paid'] ?? 0) - $yuranThreshold))
            : (float) $selectedYearTransactions->sum(fn (FamilyPaymentTransaction $transaction): float => max(0, (float) $transaction->amount - $yuranThreshold));
        $totalCollected = $tuitionCollected + $donationCollected;
        $totalBilled = $useLegacyKpiSource
            ? (float) $legacyPaymentTransactions->sum('amount_due')
            : (float) $familyBillings->sum('fee_amount');
        $totalOutstanding = $useLegacyKpiSource
            ? max(0, $totalBilled - $tuitionCollected)
            : (float) $familyBillings->sum(fn (FamilyBilling $billing) => $billing->outstanding_amount);

        if ($useLegacyKpiSource) {
            $totalFamilies = (int) $legacyPaymentTransactions->pluck('family_code')->filter()->unique()->count();
            $totalStudents = (int) $legacyPayments->pluck('student_name')->filter()->unique()->count();
            $familiesPaid = (int) $legacyPaymentTransactions
                ->groupBy(fn (array $payment): string => (string) ($payment['family_code'] ?? ''))
                ->filter(fn ($group, string $familyCode): bool => $familyCode !== '')
                ->map(fn ($group): float => (float) $group->sum('amount_paid'))
                ->filter(fn (float $paid): bool => $paid >= $yuranThreshold)
                ->count();
        }

        $paymentCompletion = $totalFamilies > 0 ? (int) round(($familiesPaid / $totalFamilies) * 100) : 0;

        if ($useLegacyKpiSource) {
            $classCollection = $legacyPaymentTransactions
                ->groupBy(fn (array $payment) => (string) ($payment['class_name'] ?? 'Unassigned'))
                ->map(function ($group, string $className) use ($yuranThreshold) {
                    $yuran = (float) $group->sum(fn (array $payment): float => min((float) ($payment['amount_paid'] ?? 0), $yuranThreshold));
                    $sumbangan = (float) $group->sum(fn (array $payment): float => max(0, (float) ($payment['amount_paid'] ?? 0) - $yuranThreshold));
                    return [
                        'class_name' => $className,
                        'yuran' => round($yuran, 2),
                        'sumbangan' => round($sumbangan, 2),
                        'collected' => round($yuran + $sumbangan, 2),
                    ];
                })
                ->sortByDesc('collected')
                ->values();
        } else {
            $dominantClassByFamily = $students
                ->filter(fn (Student $student): bool => filled($student->family_code))
                ->groupBy(fn (Student $student) => (string) $student->family_code)
                ->map(function ($familyStudents): string {
                    /** @var \Illuminate\Support\Collection<int, \App\Models\Student> $familyStudents */
                    return (string) ($familyStudents
                        ->pluck('class_name')
                        ->map(fn ($className) => trim((string) $className))
                        ->filter()
                        ->countBy()
                        ->sortDesc()
                        ->keys()
                        ->first() ?? 'Unassigned');
                });

            $classCollection = $selectedYearTransactions
                ->map(function (FamilyPaymentTransaction $transaction) use ($dominantClassByFamily, $yuranThreshold): array {
                    $familyCode = (string) ($transaction->familyBilling?->family_code ?? '');
                    $className = $dominantClassByFamily->get($familyCode, 'Unassigned');
                    $amount = (float) $transaction->amount;

                    return [
                        'class_name' => (string) ($className !== '' ? $className : 'Unassigned'),
                        'yuran' => min($amount, $yuranThreshold),
                        'sumbangan' => max(0, $amount - $yuranThreshold),
                        'collected' => $amount,
                    ];
                })
                ->groupBy('class_name')
                ->map(function ($group, string $className): array {
                    /** @var \Illuminate\Support\Collection<int, array<string, float|string>> $group */
                    $yuran = (float) $group->sum('yuran');
                    $sumbangan = (float) $group->sum('sumbangan');
                    return [
                        'class_name' => $className,
                        'yuran' => round($yuran, 2),
                        'sumbangan' => round($sumbangan, 2),
                        'collected' => round($yuran + $sumbangan, 2),
                    ];
                })
                ->sortByDesc('collected')
                ->values();
        }

        $classYearOptions = collect(range(1, 6));
        $isValidClassYearFilter = $selectedClassYearFilter === 'all'
            || $classYearOptions->map(fn (int $year) => (string) $year)->contains($selectedClassYearFilter);
        if (! $isValidClassYearFilter) {
            $selectedClassYearFilter = 'all';
        }

        $filteredClassCollection = $classCollection
            ->filter(function (array $row) use ($selectedClassYearFilter): bool {
                if ($selectedClassYearFilter === 'all') {
                    return true;
                }

                $classYear = $this->extractClassYear((string) ($row['class_name'] ?? ''));
                return $classYear !== null && (string) $classYear === $selectedClassYearFilter;
            })
            ->sortBy([
                ['collected', 'desc'],
                ['class_name', 'asc'],
            ])
            ->values();

        $classChartLabels = $filteredClassCollection->pluck('class_name')->map(fn ($label) => (string) $label)->toArray();
        $classChartCollected = $filteredClassCollection->pluck('collected')->toArray();
        $classChartYuran = $filteredClassCollection->pluck('yuran')->toArray();
        $classChartSumbangan = $filteredClassCollection->pluck('sumbangan')->toArray();

        $trendMonthLabels = collect(range(1, 12))
            ->map(fn (int $month): string => Carbon::create($selectedDashboardYear, $month, 1)->format('M'))
            ->values();

        if ($useLegacyKpiSource) {
            $monthlyPaid = $legacyPaymentTransactions
                ->filter(fn (array $payment) => ($payment['paid_at'] ?? null) !== null)
                ->groupBy(fn (array $payment) => $payment['paid_at']->format('n'))
                ->map(fn ($group) => (float) $group->sum('amount_paid'));

            $calendarPaidCountByDate = $legacyPaymentTransactions
                ->filter(fn (array $payment) => ($payment['paid_at'] ?? null) !== null)
                ->groupBy(fn (array $payment) => $payment['paid_at']->format('Y-m-d'))
                ->map(fn ($group) => $group->count())
                ->toArray();
        } else {
            $monthlyPaid = $selectedYearTransactions
                ->groupBy(fn (FamilyPaymentTransaction $transaction) => $transaction->paid_at->format('n'))
                ->map(fn ($group) => (float) $group->sum('amount'));

            $calendarPaidCountByDate = $selectedYearTransactions
                ->groupBy(fn (FamilyPaymentTransaction $transaction) => $transaction->paid_at->format('Y-m-d'))
                ->map(fn ($group) => $group->count())
                ->toArray();
        }

        $dailyTrendLabels = $trendMonthLabels->toArray();
        $dailyTrendValues = collect(range(1, 12))
            ->map(fn (int $month): float => round((float) $monthlyPaid->get((string) $month, $monthlyPaid->get($month, 0)), 2))
            ->values()
            ->toArray();

        $familyBillingsAllYears = FamilyBilling::query()
            ->with('students')
            ->orderByDesc('billing_year')
            ->get();

        $familyStatusByYearClass = [];
        foreach ($familyBillingsAllYears as $billing) {
            $yearKey = (string) $billing->billing_year;
            $isPaid = (float) $billing->outstanding_amount <= 0;
            $statusKey = $isPaid ? 'paid' : 'unpaid';

            $familyClasses = $billing->students
                ->pluck('class_name')
                ->filter()
                ->unique()
                ->values();

            if ($familyClasses->isEmpty()) {
                $familyClasses = collect(['Unassigned']);
            }

            if (! isset($familyStatusByYearClass[$yearKey]['All'])) {
                $familyStatusByYearClass[$yearKey]['All'] = ['paid' => 0, 'unpaid' => 0];
            }
            $familyStatusByYearClass[$yearKey]['All'][$statusKey]++;

            foreach ($familyClasses as $className) {
                $classKey = (string) $className;
                if (! isset($familyStatusByYearClass[$yearKey][$classKey])) {
                    $familyStatusByYearClass[$yearKey][$classKey] = ['paid' => 0, 'unpaid' => 0];
                }
                $familyStatusByYearClass[$yearKey][$classKey][$statusKey]++;
            }
        }

        $statusFilterYears = collect($familyBillingsAllYears->pluck('billing_year'))
            ->map(fn ($year) => (string) $year)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        if (empty($statusFilterYears)) {
            $statusFilterYears = [(string) $billingYear];
        }

        $legacyStatusByClassByYear = [];
        $legacyStatusYears = $legacyPayments->pluck('source_year')->map(fn ($year) => (string) $year)->unique()->values()->toArray();

        foreach ($legacyStatusYears as $legacyYear) {
            $rows = LegacyStudentPayment::query()
                ->where('source_year', (int) $legacyYear)
                ->where('payment_status', 'paid')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $allFamilies = $rows->pluck('family_code')->filter()->unique()->count();
            $legacyStatusByClassByYear[$legacyYear]['All'] = ['paid' => (int) $allFamilies, 'unpaid' => 0];

            $rowsByClass = $rows->groupBy(fn (LegacyStudentPayment $row) => (string) ($row->class_name ?: 'Unassigned'));
            foreach ($rowsByClass as $className => $classRows) {
                $legacyStatusByClassByYear[$legacyYear][$className] = [
                    'paid' => (int) $classRows->pluck('family_code')->filter()->unique()->count(),
                    'unpaid' => 0,
                ];
            }
        }

        foreach ($legacyStatusByClassByYear as $yearKey => $legacyClassStatus) {
            if (! isset($familyStatusByYearClass[$yearKey])) {
                $familyStatusByYearClass[$yearKey] = $legacyClassStatus;
            }
        }

        $statusFilterClasses = Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->pluck('class_name')
            ->merge(
                LegacyStudentPayment::query()
                    ->whereNotNull('class_name')
                    ->where('class_name', '!=', '')
                    ->pluck('class_name')
            )
            ->map(fn ($class) => (string) $class)
            ->unique()
            ->sort()
            ->values()
            ->prepend('All')
            ->toArray();

        $defaultStatusFilterYear = in_array((string) $billingYear, $statusFilterYears, true)
            ? (string) $billingYear
            : $statusFilterYears[0];

        $selectedStatusFilterYear = (string) $selectedDashboardYear;
        if (! in_array($selectedStatusFilterYear, $statusFilterYears, true)) {
            $statusFilterYears[] = $selectedStatusFilterYear;
            rsort($statusFilterYears);
        }

        $transactionsForParent = $this->parentTransactions($user);
        $transactionsByYear = $transactionsForParent
            ->filter(fn (FamilyPaymentTransaction $transaction) => $transaction->status === 'success')
            ->groupBy(fn (FamilyPaymentTransaction $transaction) => $transaction->paid_at?->year ?? now()->year);

        $calendarEvents = SchoolCalendarEvent::query()
            ->orderBy('start_date')
            ->orderBy('sort_order')
            ->get();

        return view('dashboard', [
            'role' => $role,
            'dashboardYearOptions' => $yearOptions->toArray(),
            'selectedDashboardYear' => $selectedDashboardYear,
            'useLegacyKpiSource' => $useLegacyKpiSource,
            'totalFamilies' => $totalFamilies,
            'familiesPaid' => $familiesPaid,
            'totalStudents' => $totalStudents,
            'tuitionCollected' => $tuitionCollected,
            'donationCollected' => $donationCollected,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'totalBilled' => $totalBilled,
            'paymentCompletion' => $paymentCompletion,
            'classChartLabels' => $classChartLabels,
            'classChartCollected' => $classChartCollected,
            'classChartYuran' => $classChartYuran,
            'classChartSumbangan' => $classChartSumbangan,
            'classYearOptions' => $classYearOptions->toArray(),
            'selectedClassYearFilter' => $selectedClassYearFilter,
            'dailyTrendLabels' => $dailyTrendLabels,
            'dailyTrendValues' => $dailyTrendValues,
            'calendarPaidCountByDate' => $calendarPaidCountByDate,
            'familyStatusByYearClass' => $familyStatusByYearClass,
            'statusFilterYears' => $statusFilterYears,
            'statusFilterClasses' => $statusFilterClasses,
            'defaultStatusFilterYear' => $defaultStatusFilterYear,
            'selectedStatusFilterYear' => $selectedStatusFilterYear,
            'transactions' => $transactionsForParent,
            'transactionsByYear' => $transactionsByYear,
            'calendarEvents' => $calendarEvents,
        ]);
    }

    public function submitParentMessage(Request $request): RedirectResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $entry = sprintf(
            "[%s] %s (%s): %s\\n",
            now()->format('Y-m-d H:i:s'),
            $user?->name ?? 'Guest',
            $user?->email ?? 'anonymous',
            $request->input('message')
        );

        File::append(storage_path('logs/parent_messages.log'), $entry);

        $portalUrl = route('home');

        $waText = "Assalamualaikum Bendahari PIBG,

"
            .$request->input('message')
            ."

Daripada:
"
            .($user?->name ?? 'Guest')
            .' ('.($user?->email ?? 'anonymous').')'
            ."

Portal Yuran:
"
            .$portalUrl;

        $treasuryPhone = $this->normalizeWaPhone((string) config('services.treasury_whatsapp_phone', '60136454001'));

        return redirect()->away('https://wa.me/'.$treasuryPhone.'?text='.rawurlencode($waText));
    }

    private function parentTransactions($user)
    {
        $query = FamilyPaymentTransaction::query()->with('familyBilling')->latest('paid_at');

        if ($user) {
            $query->where(function ($builder) use ($user) {
                $builder->where('user_id', $user->id);

                if ($user->phone) {
                    $builder->orWhere('payer_phone', $user->phone);
                }

                if ($user->email) {
                    $builder->orWhere('payer_email', $user->email);
                }
            });
        }

        $transactions = $query->limit(5)->get();

        if ($transactions->isEmpty()) {
            $transactions = FamilyPaymentTransaction::query()
                ->with('familyBilling')
                ->latest('paid_at')
                ->limit(5)
                ->get();
        }

        return $transactions;
    }

    private function normalizeWaPhone(string $phone): string
    {
        $digits = preg_replace('/\\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '60136454001';
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

    private function extractClassYear(string $className): ?int
    {
        if (preg_match('/^\s*(\d{1,2})\b/', $className, $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);
        return $year > 0 ? $year : null;
    }
}
