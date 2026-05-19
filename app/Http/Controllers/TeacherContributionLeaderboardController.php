<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TeacherContributionLeaderboardController extends Controller
{
    public function index(): View
    {
        $billingYear = (int) now()->year;

        $students = Student::query()
            ->active()
            ->where('billing_year', $billingYear)
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->get(['family_code', 'class_name', 'annual_fee']);

        $familyCodes = $students
            ->pluck('family_code')
            ->map(fn ($familyCode): string => trim((string) $familyCode))
            ->filter()
            ->unique()
            ->values();

        $paidFamilyMap = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->whereIn('family_code', $familyCodes->all())
            ->get(['family_code', 'status', 'fee_amount', 'paid_amount'])
            ->mapWithKeys(function (FamilyBilling $billing): array {
                $feeAmount = (float) $billing->fee_amount;
                $paidAmount = (float) $billing->paid_amount;
                $isPaid = $billing->status === 'paid' || ($feeAmount > 0 && $paidAmount >= $feeAmount);

                return [(string) $billing->family_code => $isPaid];
            });

        $classTargets = $students
            ->groupBy(fn (Student $student): string => trim((string) $student->class_name))
            ->map(fn (Collection $group): float => (float) $group->sum(fn (Student $student): float => max(0, (float) $student->annual_fee)));

        $classStudentCounts = $students
            ->groupBy(fn (Student $student): string => trim((string) $student->class_name))
            ->map(function (Collection $group) use ($paidFamilyMap): array {
                $totalStudents = $group->count();
                $paidStudents = $group
                    ->filter(fn (Student $student): bool => (bool) $paidFamilyMap->get(trim((string) $student->family_code), false))
                    ->count();

                return [
                    'paid_students' => $paidStudents,
                    'total_students' => $totalStudents,
                ];
            });

        $dominantClassByFamily = $students
            ->filter(fn (Student $student): bool => filled($student->family_code))
            ->groupBy(fn (Student $student): string => (string) $student->family_code)
            ->map(function (Collection $familyStudents): string {
                return (string) ($familyStudents
                    ->pluck('class_name')
                    ->map(fn ($className) => trim((string) $className))
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first() ?? '');
            });

        $transactions = FamilyPaymentTransaction::query()
            ->with('familyBilling:id,family_code,billing_year')
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->whereHas('familyBilling', fn ($query) => $query->where('billing_year', $billingYear))
            ->get();

        $totalsByClass = $transactions->reduce(function (Collection $carry, FamilyPaymentTransaction $transaction) use ($dominantClassByFamily): Collection {
            $familyCode = (string) ($transaction->familyBilling?->family_code ?? '');
            if ($familyCode === '') {
                return $carry;
            }

            $className = (string) ($dominantClassByFamily->get($familyCode) ?? '');
            if ($className === '') {
                return $carry;
            }

            $current = (array) $carry->get($className, [
                'without_donation' => 0.0,
                'with_donation' => 0.0,
            ]);

            $current['without_donation'] += max(0, (float) ($transaction->fee_amount_paid ?? 0));
            $current['with_donation'] += max(0, (float) ($transaction->amount ?? 0));

            $carry->put($className, $current);

            return $carry;
        }, collect());

        $leaderboard = $classTargets
            ->map(function (float $targetTotal, string $className) use ($totalsByClass, $classStudentCounts): array {
                $totals = (array) $totalsByClass->get($className, []);
                $withoutDonation = (float) ($totals['without_donation'] ?? 0);
                $withDonation = (float) ($totals['with_donation'] ?? 0);

                $classCounts = (array) $classStudentCounts->get($className, []);
                $paidStudents = (int) ($classCounts['paid_students'] ?? 0);
                $totalStudents = (int) ($classCounts['total_students'] ?? 0);

                // Match Teacher Class Progress exactly for "Tanpa sumbangan" percentage.
                $withoutPercent = $totalStudents > 0 ? round(($paidStudents / $totalStudents) * 100, 1) : 0.0;
                $withPercent = $targetTotal > 0 ? round(($withDonation / $targetTotal) * 100, 1) : 0.0;

                return [
                    'class_name' => $className,
                    'tahap' => $this->resolveTahapFromClassName($className),
                    'target_total' => $targetTotal,
                    'without_donation_total' => $withoutDonation,
                    'with_donation_total' => $withDonation,
                    'without_donation_percent' => $withoutPercent,
                    'with_donation_percent' => $withPercent,
                    'paid_students' => $paidStudents,
                    'total_students' => $totalStudents,
                ];
            })
            ->sortBy([
                ['without_donation_percent', 'desc'],
                ['with_donation_percent', 'desc'],
                ['class_name', 'asc'],
            ])
            ->values();

        $groupedByTahap = collect([
            'Tahap 1' => $leaderboard->where('tahap', 'Tahap 1')->values(),
            'Tahap 2' => $leaderboard->where('tahap', 'Tahap 2')->values(),
        ]);

        return view('teacher.contribution-leaderboard', [
            'billingYear' => $billingYear,
            'groupedByTahap' => $groupedByTahap,
        ]);
    }

    private function resolveTahapFromClassName(string $className): string
    {
        if (preg_match('/^(\d+)/', trim($className), $matches) !== 1) {
            return 'Tahap 1';
        }

        $year = (int) ($matches[1] ?? 1);

        return $year >= 4 ? 'Tahap 2' : 'Tahap 1';
    }
}
