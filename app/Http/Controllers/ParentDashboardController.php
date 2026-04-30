<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Support\ParentPhone;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ParentDashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $parentUser = $request->user();
        $parentPhone = $parentUser?->phone;
        $billingYear = now()->year;
        $isTesterMode = (bool) $parentUser?->isParentTester();

        $accessibleFamilyCodes = $this->resolveAccessibleFamilyCodes($parentPhone);

        $children = Student::query()
            ->whereIn('family_code', $accessibleFamilyCodes)
            ->orderBy('full_name')
            ->get();

        $familyCodes = $children
            ->pluck('family_code')
            ->filter(fn ($code) => filled($code))
            ->unique()
            ->values();

        $studentIds = $children->pluck('id')->filter()->values();
        $childNames = $children
            ->pluck('full_name')
            ->map(fn ($name) => $this->normalizeNameForLegacyMatch((string) $name))
            ->filter()
            ->unique()
            ->values();

        $familyBillings = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->whereIn('family_code', $familyCodes)
            ->orderBy('family_code')
            ->get();

        $latestPaidYear = FamilyBilling::query()
            ->whereIn('family_code', $familyCodes)
            ->where(function ($query) {
                $query->where('status', 'paid')
                    ->orWhereColumn('paid_amount', '>=', 'fee_amount');
            })
            ->max('billing_year');

        $hasAdditionalDonationForLatestPaidYear = false;
        if ($latestPaidYear) {
            $hasAdditionalDonationForLatestPaidYear = FamilyPaymentTransaction::query()
                ->where('status', 'success')
                ->where('donation_amount', '>', 0)
                ->whereHas('familyBilling', fn ($query) => $query
                    ->whereIn('family_code', $familyCodes)
                    ->where('billing_year', (int) $latestPaidYear))
                ->exists();
        }

        $legacyPayments = LegacyStudentPayment::query()
            ->where(function ($nested) use ($familyCodes, $studentIds) {
                $nested->whereIn('family_code', $familyCodes);

                if ($studentIds->isNotEmpty()) {
                    $nested->orWhereIn('student_id', $studentIds->all());
                }
            })
            ->where('payment_status', 'paid')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(function (LegacyStudentPayment $payment) use ($familyCodes, $studentIds, $childNames): bool {
                if ($payment->student_id !== null && $studentIds->contains((int) $payment->student_id)) {
                    return true;
                }

                if (! $familyCodes->contains((string) $payment->family_code)) {
                    return false;
                }

                $legacyName = $this->normalizeNameForLegacyMatch((string) $payment->student_name);
                return $legacyName !== '' && $childNames->contains($legacyName);
            })
            ->map(function (LegacyStudentPayment $payment): LegacyStudentPayment {
                $rawClass = trim((string) data_get($payment->raw_payload, 'class_name', ''));
                $storedClass = trim((string) ($payment->class_name ?? ''));
                $displayClass = $rawClass !== '' ? $rawClass : $storedClass;
                $payment->setAttribute('display_class_name', mb_strtoupper($displayClass));

                return $payment;
            })
            ->values();

        $successfulTransactions = FamilyPaymentTransaction::query()
            ->with('familyBilling:id,family_code,billing_year')
            ->where('status', 'success')
            ->whereHas('familyBilling', fn ($query) => $query->whereIn('family_code', $familyCodes))
            ->latest('id')
            ->get();

        $latestCurrentYearReceipt = $successfulTransactions
            ->first(fn (FamilyPaymentTransaction $transaction): bool => (int) ($transaction->familyBilling?->billing_year ?? 0) === $billingYear);

        $latestPastYearReceipt = $successfulTransactions
            ->first(fn (FamilyPaymentTransaction $transaction): bool => (int) ($transaction->familyBilling?->billing_year ?? 0) < $billingYear);
        $currentYearReceiptByFamily = $successfulTransactions
            ->filter(fn (FamilyPaymentTransaction $transaction): bool => (int) ($transaction->familyBilling?->billing_year ?? 0) === $billingYear)
            ->groupBy(fn (FamilyPaymentTransaction $transaction): string => (string) ($transaction->familyBilling?->family_code ?? ''))
            ->map(fn (Collection $group) => $group->first())
            ->filter();

        $competitionStart = Carbon::create($billingYear, 4, 14, 0, 0, 0)->startOfWeek();
        $competitionStudents = Student::query()
            ->where('billing_year', $billingYear)
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->get(['family_code', 'class_name', 'annual_fee']);

        $dominantClassByFamily = $competitionStudents
            ->filter(fn (Student $student): bool => filled($student->family_code))
            ->groupBy(fn (Student $student): string => (string) $student->family_code)
            ->map(function ($familyStudents): string {
                return (string) ($familyStudents
                    ->pluck('class_name')
                    ->map(fn ($className) => trim((string) $className))
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first() ?? '');
            });

        $overallYuranTotal = (float) $competitionStudents->sum(fn (Student $student): float => max(0, (float) $student->annual_fee));
        $overallYuranPaid = (float) FamilyPaymentTransaction::query()
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$competitionStart->copy()->startOfDay(), now()->endOfDay()])
            ->whereHas('familyBilling', fn ($query) => $query->where('billing_year', $billingYear))
            ->sum('fee_amount_paid');
        $overallYuranPercentage = $overallYuranTotal > 0
            ? round(($overallYuranPaid / $overallYuranTotal) * 100, 1)
            : 0.0;

        $classTargets = $competitionStudents
            ->groupBy(fn (Student $student): string => trim((string) $student->class_name))
            ->map(fn ($group): float => (float) $group->sum(fn (Student $student): float => max(0, (float) $student->annual_fee)));

        $paidByClass = FamilyPaymentTransaction::query()
            ->with('familyBilling:id,family_code,billing_year')
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$competitionStart->copy()->startOfDay(), now()->endOfDay()])
            ->whereHas('familyBilling', fn ($query) => $query->where('billing_year', $billingYear))
            ->get()
            ->reduce(function (Collection $carry, FamilyPaymentTransaction $transaction) use ($dominantClassByFamily): Collection {
                $familyCode = (string) ($transaction->familyBilling?->family_code ?? '');
                if ($familyCode === '') {
                    return $carry;
                }
                $className = (string) ($dominantClassByFamily->get($familyCode) ?? '');
                if ($className === '') {
                    return $carry;
                }
                $feePaid = max(0, (float) ($transaction->fee_amount_paid ?? $transaction->amount ?? 0));
                $carry->put($className, ((float) $carry->get($className, 0)) + $feePaid);
                return $carry;
            }, collect());

        $classCompetition = $classTargets
            ->map(function (float $targetTotal, string $className) use ($paidByClass): array {
                $paidFeeTotal = (float) $paidByClass->get($className, 0);
                return [
                    'class_name' => $className,
                    'percentage' => $targetTotal > 0 ? round(($paidFeeTotal / $targetTotal) * 100, 2) : 0.0,
                    'tahap' => $this->resolveTahapFromClassName($className),
                ];
            })
            ->sortBy([
                ['percentage', 'desc'],
            ])
            ->values();

        $classCompetitionByTahap = collect([
            'Tahap 1' => $classCompetition->where('tahap', 'Tahap 1')->take(5)->values(),
            'Tahap 2' => $classCompetition->where('tahap', 'Tahap 2')->take(5)->values(),
        ]);

        return view('parent.dashboard', [
            'children' => $children,
            'familyBillings' => $familyBillings,
            'billingYear' => $billingYear,
            'isTesterMode' => $isTesterMode,
            'totalOutstanding' => (float) $familyBillings->sum(fn (FamilyBilling $billing): float => $billing->outstanding_amount),
            'latestPaidYear' => $latestPaidYear ? (int) $latestPaidYear : null,
            'hasAdditionalDonationForLatestPaidYear' => $hasAdditionalDonationForLatestPaidYear,
            'legacyPayments' => $legacyPayments,
            'legacyPaidTotal' => (float) $legacyPayments->sum('amount_paid'),
            'legacyDonationTotal' => (float) $legacyPayments->sum('donation_amount'),
            'recentPaymentToasts' => $this->buildRecentPaymentToasts(),
            'overallYuranTotal' => $overallYuranTotal,
            'overallYuranPaid' => $overallYuranPaid,
            'overallYuranPercentage' => $overallYuranPercentage,
            'classCompetitionByTahap' => $classCompetitionByTahap,
            'latestCurrentYearReceipt' => $latestCurrentYearReceipt,
            'latestPastYearReceipt' => $latestPastYearReceipt,
            'currentYearReceiptByFamily' => $currentYearReceiptByFamily,
        ]);
    }

    public function legacyReceiptPdf(Request $request): Response
    {
        $parentPhone = $request->user()?->phone;
        $accessibleFamilyCodes = $this->resolveAccessibleFamilyCodes($parentPhone);

        $children = Student::query()
            ->whereIn('family_code', $accessibleFamilyCodes)
            ->orderBy('full_name')
            ->get();

        $familyCodes = $children
            ->pluck('family_code')
            ->filter(fn ($code) => filled($code))
            ->unique()
            ->values();

        $studentIds = $children->pluck('id')->filter()->values();
        $childNames = $children
            ->pluck('full_name')
            ->map(fn ($name) => $this->normalizeNameForLegacyMatch((string) $name))
            ->filter()
            ->unique()
            ->values();

        $legacyPayments = LegacyStudentPayment::query()
            ->where(function ($nested) use ($familyCodes, $studentIds) {
                $nested->whereIn('family_code', $familyCodes);
                if ($studentIds->isNotEmpty()) {
                    $nested->orWhereIn('student_id', $studentIds->all());
                }
            })
            ->where('payment_status', 'paid')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->filter(function (LegacyStudentPayment $payment) use ($familyCodes, $studentIds, $childNames): bool {
                if ($payment->student_id !== null && $studentIds->contains((int) $payment->student_id)) {
                    return true;
                }

                if (! $familyCodes->contains((string) $payment->family_code)) {
                    return false;
                }

                $legacyName = $this->normalizeNameForLegacyMatch((string) $payment->student_name);
                return $legacyName !== '' && $childNames->contains($legacyName);
            })
            ->map(function (LegacyStudentPayment $payment): LegacyStudentPayment {
                $rawClass = trim((string) data_get($payment->raw_payload, 'class_name', ''));
                $storedClass = trim((string) ($payment->class_name ?? ''));
                $displayClass = $rawClass !== '' ? $rawClass : $storedClass;
                $payment->setAttribute('display_class_name', mb_strtoupper($displayClass));

                return $payment;
            })
            ->values();

        abort_if($legacyPayments->isEmpty(), 404);

        $selectedYear = (int) $request->integer('year', (int) $legacyPayments->max('source_year'));
        $legacyPaymentsByYear = $legacyPayments
            ->where('source_year', $selectedYear)
            ->values();

        abort_if($legacyPaymentsByYear->isEmpty(), 404);

        $totals = [
            'paid' => (float) $legacyPaymentsByYear->sum('amount_paid'),
            'donation' => (float) $legacyPaymentsByYear->sum('donation_amount'),
        ];

        $schoolLogoUrl = SiteSetting::schoolLogoUrl();
        $schoolLogoPdfSource = $this->schoolLogoPdfSource($schoolLogoUrl);

        $pdf = Pdf::loadView('parent.legacy-receipt-pdf', [
            'selectedYear' => $selectedYear,
            'legacyPayments' => $legacyPaymentsByYear,
            'totals' => $totals,
            'children' => $children,
            'schoolLogoUrl' => $schoolLogoUrl,
            'schoolLogoPdfSource' => $schoolLogoPdfSource,
            'generatedAt' => now(),
        ])->setPaper('a4');

        $rujukanSuffix = $this->legacyReceiptRujukanSuffix($legacyPaymentsByYear);
        return $pdf->download("resit-sejarah-bayaran-{$selectedYear}{$rujukanSuffix}.pdf");
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
    private function legacyReceiptRujukanSuffix(Collection $payments): string
    {
        $rujukan = (string) ($payments
            ->pluck('payment_reference')
            ->filter(fn ($value): bool => filled($value))
            ->first() ?? '');

        $sanitized = trim((string) preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower($rujukan)));

        return $sanitized !== '' ? '-rujukan-'.$sanitized : '';
    }

    public function classProgress(Request $request): View
    {
        $billingYear = (int) now()->year;
        $competitionStart = Carbon::create($billingYear, 4, 14, 0, 0, 0)->startOfWeek();
        $currentWeekStart = now()->startOfWeek();

        $weekStarts = collect();
        for ($cursor = $competitionStart->copy(); $cursor->lte($currentWeekStart); $cursor->addWeek()) {
            $weekStarts->push($cursor->copy());
        }
        if ($weekStarts->isEmpty()) {
            $weekStarts->push($currentWeekStart->copy());
        }

        $selectedWeekStartInput = (string) $request->query('week_start', $weekStarts->last()->toDateString());
        $selectedWeekStart = $weekStarts
            ->first(fn (Carbon $week): bool => $week->toDateString() === $selectedWeekStartInput)
            ?? $weekStarts->last();
        $selectedWeekEnd = $selectedWeekStart->copy()->addDays(6)->endOfDay();

        $students = Student::query()
            ->where('billing_year', $billingYear)
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->get(['family_code', 'class_name', 'annual_fee']);

        $classTargets = $students
            ->groupBy(fn (Student $student): string => trim((string) $student->class_name))
            ->map(fn ($group): float => (float) $group->sum(fn (Student $student): float => max(0, (float) $student->annual_fee)));

        $dominantClassByFamily = $students
            ->filter(fn (Student $student): bool => filled($student->family_code))
            ->groupBy(fn (Student $student): string => (string) $student->family_code)
            ->map(function ($familyStudents): string {
                return (string) ($familyStudents
                    ->pluck('class_name')
                    ->map(fn ($className) => trim((string) $className))
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first() ?? '');
            });

        $weeklyTransactions = FamilyPaymentTransaction::query()
            ->with('familyBilling:id,family_code,billing_year')
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$competitionStart->copy()->startOfDay(), $selectedWeekEnd])
            ->whereHas('familyBilling', fn ($query) => $query->where('billing_year', $billingYear))
            ->get();

        $weeklyPaidByClass = $weeklyTransactions
            ->reduce(function (Collection $carry, FamilyPaymentTransaction $transaction) use ($dominantClassByFamily): Collection {
                $familyCode = (string) ($transaction->familyBilling?->family_code ?? '');
                if ($familyCode === '') {
                    return $carry;
                }

                $className = (string) ($dominantClassByFamily->get($familyCode) ?? '');
                if ($className === '') {
                    return $carry;
                }

                $paid = max(0, (float) ($transaction->fee_amount_paid ?? $transaction->amount ?? 0));
                $carry->put($className, ((float) $carry->get($className, 0)) + $paid);

                return $carry;
            }, collect());

        $classProgress = $classTargets
            ->map(function (float $targetTotal, string $className) use ($weeklyPaidByClass): array {
                $paidTotal = (float) $weeklyPaidByClass->get($className, 0);
                $percentage = $targetTotal > 0 ? round(($paidTotal / $targetTotal) * 100, 1) : 0.0;

                return [
                    'class_name' => $className,
                    'percentage' => max(0, min(100, $percentage)),
                    'paid_total' => $paidTotal,
                    'target_total' => $targetTotal,
                    'tahap' => $this->resolveTahapFromClassName($className),
                ];
            })
            ->sortBy([
                ['percentage', 'desc'],
                ['paid_total', 'desc'],
            ])
            ->values();

        $classProgressByTahap = collect([
            'Tahap 1' => $classProgress->where('tahap', 'Tahap 1')->values(),
            'Tahap 2' => $classProgress->where('tahap', 'Tahap 2')->values(),
        ]);

        return view('parent.class-progress', [
            'weekOptions' => $weekStarts->map(fn (Carbon $week) => [
                'value' => $week->toDateString(),
                'label' => 'Minggu '.$week->format('d M').' - '.$week->copy()->addDays(6)->format('d M Y'),
            ]),
            'selectedWeekStart' => $selectedWeekStart->toDateString(),
            'selectedWeekLabel' => 'Minggu '.$selectedWeekStart->format('d M').' - '.$selectedWeekStart->copy()->addDays(6)->format('d M Y'),
            'classProgressByTahap' => $classProgressByTahap,
        ]);
    }

    private function resolveTahapFromClassName(string $className): string
    {
        $trimmed = trim($className);
        if ($trimmed === '') {
            return 'Tahap 1';
        }

        $firstChar = mb_substr($trimmed, 0, 1);
        $year = (int) preg_replace('/\D/', '', $firstChar);

        return $year >= 4 ? 'Tahap 2' : 'Tahap 1';
    }

    private function buildRecentPaymentToasts(): Collection
    {
        $recentTransactions = FamilyPaymentTransaction::query()
            ->with('familyBilling:id,family_code,billing_year')
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get();

        $familyCodes = $recentTransactions
            ->pluck('familyBilling.family_code')
            ->filter()
            ->unique()
            ->values();

        $dominantClassByFamily = Student::query()
            ->whereIn('family_code', $familyCodes)
            ->select(['family_code', 'class_name'])
            ->get()
            ->groupBy('family_code')
            ->map(function ($familyStudents): string {
                return (string) ($familyStudents
                    ->pluck('class_name')
                    ->map(fn ($className) => trim((string) $className))
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first() ?? 'Unknown Class');
            });

        return $recentTransactions
            ->map(function (FamilyPaymentTransaction $transaction) use ($dominantClassByFamily): ?string {
                $familyCode = (string) ($transaction->familyBilling?->family_code ?? '');
                if ($familyCode === '') {
                    return null;
                }

                $className = (string) ($dominantClassByFamily->get($familyCode) ?: 'Unknown Class');
                $donation = (float) ($transaction->donation_amount ?? 0);

                if ($donation <= 0) {
                    $donation = max(0, (float) $transaction->amount - (float) ($transaction->fee_amount_paid ?? 0));
                }

                if ($donation > 0) {
                    return "Parent in {$className} just paid Yuran + Sumbangan Tambahan";
                }

                return "Parent in {$className} just paid Yuran";
            })
            ->filter()
            ->unique()
            ->take(10)
            ->values();
    }

    private function normalizeNameForLegacyMatch(string $name): string
    {
        $value = mb_strtoupper(trim($name));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim((string) $value);
    }

    /**
     * @return Collection<int, string>
     */
    private function resolveAccessibleFamilyCodes(?string $phone): Collection
    {
        $normalizedPhone = ParentPhone::normalizeForMatch((string) $phone);

        if ($normalizedPhone === '') {
            return collect();
        }

        $studentFamilyCodes = Student::query()
            ->whereIn('parent_phone', ParentPhone::variants((string) $phone))
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
}
