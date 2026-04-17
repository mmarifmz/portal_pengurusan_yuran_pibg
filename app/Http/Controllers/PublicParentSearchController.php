<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicParentSearchController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Collection<int, array<string, mixed>> $familyResults */
        $familyResults = collect();
        $searchBillings = collect();
        $hasSearched = false;
        $totalFamilyResults = 0;
        $visibleLimit = max(20, min((int) $request->integer('visible_limit', 20), 200));

        if ($request->filled('student_keyword') || $request->filled('class_name') || $request->filled('contact')) {
            $validated = $request->validate([
                'student_keyword' => ['nullable', 'string', 'max:100', 'required_without_all:class_name,contact'],
                'class_name' => ['nullable', 'string', 'max:50', 'required_without_all:student_keyword,contact'],
                'contact' => ['nullable', 'string', 'max:30', 'required_without_all:student_keyword,class_name'],
            ]);

            $hasSearched = true;

            /** @var Collection<int, Student> $students */
            $normalizedContact = isset($validated['contact']) ? preg_replace('/\D+/', '', (string) $validated['contact']) : null;

            $students = Student::query()
                ->when($validated['student_keyword'] ?? null, function ($query, $keyword) {
                    $query->where(function ($nested) use ($keyword) {
                        $nested->where('full_name', 'like', "%{$keyword}%")
                            ->orWhere('student_no', 'like', "%{$keyword}%")
                            ->orWhere('class_name', 'like', "%{$keyword}%");
                    });
                })
                ->when($validated['class_name'] ?? null, function ($query, $className) {
                    $query->where('class_name', $className);
                })
                ->orderByRaw('CASE WHEN family_code IS NULL OR family_code = "" THEN 1 ELSE 0 END')
                ->orderBy('family_code')
                ->orderBy('full_name')
                ->take(300)
                ->get();

            if ($normalizedContact) {
                $students = $students
                    ->filter(function (Student $student) use ($normalizedContact): bool {
                        $studentPhone = preg_replace('/\D+/', '', (string) $student->parent_phone) ?? '';

                        return $studentPhone !== '' && $studentPhone === $normalizedContact;
                    })
                    ->values();
            }

            $searchBillings = FamilyBilling::query()
                ->whereIn('family_code', $students->pluck('family_code')->filter()->unique())
                ->orderByDesc('billing_year')
                ->orderByDesc('id')
                ->get()
                ->unique('family_code')
                ->keyBy('family_code');

            $familyResults = $students
                ->groupBy(fn (Student $student) => $student->family_code ?: 'NO_FAMILY::'.$student->id)
                ->map(function (Collection $group, string $groupKey) use ($searchBillings): array {
                    /** @var Student $primaryStudent */
                    $primaryStudent = $group->first();
                    $familyCode = $groupKey !== '' && ! str_starts_with($groupKey, 'NO_FAMILY::') ? $groupKey : null;
                    $billing = $familyCode ? ($searchBillings[$familyCode] ?? null) : null;

                    return [
                        'group_key' => $groupKey,
                        'family_code' => $familyCode,
                        'billing' => $billing,
                        'parent_phone' => $group->pluck('parent_phone')->filter()->first(),
                        'masked_parent_phone' => $this->maskParentPhonePrefix(
                            $group->pluck('parent_phone')->filter()->first()
                        ),
                        'has_registered_phone' => $group->pluck('parent_phone')->filter()->isNotEmpty(),
                        'student_count' => $group->count(),
                        'classes' => $group->pluck('class_name')->filter()->unique()->values(),
                        'students' => $group
                            ->sortBy('full_name')
                            ->values()
                            ->map(function (Student $student): array {
                                return [
                                    'student_no' => $student->student_no,
                                    'full_name' => $student->full_name,
                                    'masked_name' => $this->maskStudentName($student->full_name),
                                    'class_name' => $student->class_name,
                                ];
                            }),
                        'sort_name' => strtolower((string) $primaryStudent->full_name),
                    ];
                })
                ->sortBy([
                    ['family_code', 'asc'],
                    ['sort_name', 'asc'],
                ])
                ->values();

            $totalFamilyResults = $familyResults->count();
            $familyResults = $familyResults->take($visibleLimit)->values();
        }

        $availableClasses = Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->values();

        return view('parent.search', [
            'familyResults' => $familyResults,
            'searchBillings' => $searchBillings,
            'hasSearched' => $hasSearched,
            'totalFamilyResults' => $totalFamilyResults,
            'visibleLimit' => $visibleLimit,
            'availableClasses' => $availableClasses,
        ]);
    }

    private function maskStudentName(string $fullName): string
    {
        $trimmed = trim($fullName);

        if ($trimmed === '') {
            return '-';
        }

        $length = mb_strlen($trimmed);
        $visibleLength = max(3, (int) ceil($length * 0.6));
        $maskedLength = max(1, $length - $visibleLength);

        return mb_substr($trimmed, 0, $visibleLength).str_repeat('#', $maskedLength);
    }

    private function maskParentPhonePrefix(?string $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 4) {
            return str_repeat('#', strlen($digits));
        }

        return substr($digits, 0, max(0, strlen($digits) - 4)).'####';
    }
}
