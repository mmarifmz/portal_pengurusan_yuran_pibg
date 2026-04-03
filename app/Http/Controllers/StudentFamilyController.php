<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\Student;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StudentFamilyController extends Controller
{
    public function index(): View
    { $billingYear = now()->year;

        $students = Student::query()
            ->orderBy('family_code')
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get();

        $familySummaries = $students
            ->filter(fn (Student $student) => filled($student->family_code))
            ->groupBy('family_code')
            ->mapWithKeys(function ($group, string $familyCode) {
                $childrenList = $group->map(fn (Student $student) => [
                    'full_name' => $student->full_name,
                    'class_name' => $student->class_name,
                    'status' => $student->status,
                ]);

                $guardianName = $group->pluck('parent_name')->filter()->first() ?? '—';
                $classNames = $group->pluck('class_name')->filter()->unique()->sort()->values()->join(', ');

                $comments = $group
                    ->pluck('status')
                    ->filter(fn (string $status) => $status !== 'active')
                    ->unique()
                    ->values();

                $missingParent = $group->first(fn (Student $student) => blank($student->parent_name) && blank($student->parent_phone));
                if ($missingParent) {
                    $comments->push('Missing parent contact');
                }

                $comment = $comments->isNotEmpty()
                    ? implode(', ', $comments->toArray())
                    : 'No issues';

                return [
                    $familyCode => [
                        'family_code' => $familyCode,
                        'children' => $group->count(),
                        'children_list' => $childrenList,
                        'comment' => $comment,
                        'guardian' => $guardianName,
                        'classes' => $classNames,
                    ],
                ];
            });

        $familyBillings = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->whereIn('family_code', $familySummaries->keys())
            ->orderBy('family_code')
            ->get()
            ->keyBy('family_code');

        $familyRecords = $familySummaries->map(function (array $entry, string $familyCode) use ($familyBillings) {
            $billing = $familyBillings->get($familyCode);

            return [
                'family_code' => $familyCode,
                'guardian' => $entry['guardian'],
                'children' => $entry['children'],
                'children_list' => $entry['children_list'],
                'classes' => $entry['classes'],
                'comment' => $entry['comment'],
                'amount_due' => $billing?->fee_amount ? (float) $billing->fee_amount : 0.0,
                'status' => Str::headline($billing?->status ?? 'unbilled'),
                'billing_status' => $billing?->status,
            ];
        })->values();

        $classFilters = $students->pluck('class_name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $statusFilters = $familyRecords
            ->pluck('status')
            ->merge(
                $familyRecords
                    ->pluck('comment')
                    ->flatMap(fn (string $comment) => collect(explode(',', $comment))
                        ->map(fn (string $entry) => trim($entry))
                        ->filter()
                    )
            )
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $totalFamilies = $familySummaries->count();
        $totalStudents = $students->count();
        $totalOutstanding = (float) $familyBillings->sum(fn (FamilyBilling $billing) => $billing->outstanding_amount);
        $totalBilled = (float) $familyBillings->sum('fee_amount');

        return view('students.family-list', [
            'billingYear' => $billingYear,
            'familyRecords' => $familyRecords,
            'familyBillings' => $familyBillings,
            'totalFamilies' => $totalFamilies,
            'totalStudents' => $totalStudents,
            'totalBilled' => $totalBilled,
            'totalOutstanding' => $totalOutstanding,
            'classFilters' => $classFilters,
            'statusFilters' => $statusFilters,
        ]);
    }
}
