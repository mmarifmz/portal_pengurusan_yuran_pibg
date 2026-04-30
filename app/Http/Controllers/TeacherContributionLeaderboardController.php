<?php

namespace App\Http\Controllers;

use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TeacherContributionLeaderboardController extends Controller
{
    public function index(): View
    {
        $billingYear = (int) now()->year;
        $competitionStart = Carbon::create($billingYear, 4, 14, 0, 0, 0)->startOfWeek();

        $students = Student::query()
            ->where('billing_year', $billingYear)
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->get(['family_code', 'class_name', 'annual_fee']);

        $classTargets = $students
            ->groupBy(fn (Student $student): string => trim((string) $student->class_name))
            ->map(fn (Collection $group): float => (float) $group->sum(fn (Student $student): float => max(0, (float) $student->annual_fee)));

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
            ->whereBetween('paid_at', [$competitionStart->copy()->startOfDay(), now()->endOfDay()])
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
            ->map(function (float $targetTotal, string $className) use ($totalsByClass): array {
                $totals = (array) $totalsByClass->get($className, []);
                $withoutDonation = (float) ($totals['without_donation'] ?? 0);
                $withDonation = (float) ($totals['with_donation'] ?? 0);

                $withoutPercent = $targetTotal > 0 ? round(($withoutDonation / $targetTotal) * 100, 1) : 0.0;
                $withPercent = $targetTotal > 0 ? round(($withDonation / $targetTotal) * 100, 1) : 0.0;

                return [
                    'class_name' => $className,
                    'tahap' => $this->resolveTahapFromClassName($className),
                    'target_total' => $targetTotal,
                    'without_donation_total' => $withoutDonation,
                    'with_donation_total' => $withDonation,
                    'without_donation_percent' => $withoutPercent,
                    'with_donation_percent' => $withPercent,
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
            'competitionStart' => $competitionStart,
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