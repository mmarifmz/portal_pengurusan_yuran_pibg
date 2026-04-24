<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TeacherSocialTagController extends Controller
{
    public function index(Request $request): View
    {
        $currentYear = (int) now()->year;

        $yearOptions = Student::query()
            ->whereNotNull('billing_year')
            ->where('billing_year', '<=', $currentYear)
            ->select('billing_year')
            ->distinct()
            ->orderByDesc('billing_year')
            ->pluck('billing_year')
            ->map(fn ($year): int => (int) $year)
            ->values();

        if ($yearOptions->isEmpty()) {
            $yearOptions = collect([$currentYear]);
        }

        $selectedYear = (int) $request->integer('billing_year', (int) $yearOptions->first());
        if (! $yearOptions->contains($selectedYear)) {
            $selectedYear = (int) $yearOptions->first();
        }

        $availableClasses = Student::query()
            ->where('billing_year', $selectedYear)
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->select('class_name')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->map(fn ($className): string => trim((string) $className))
            ->filter()
            ->values();

        $selectedClass = trim((string) $request->query('class_name', 'all'));
        if ($selectedClass !== 'all' && ! $availableClasses->contains($selectedClass)) {
            $selectedClass = 'all';
        }

        $socialTagLabels = $this->enabledSocialTagLabels();
        $tagFields = collect(array_keys($socialTagLabels));

        $students = Student::query()
            ->where('billing_year', $selectedYear)
            ->when($selectedClass !== 'all', fn ($query) => $query->where('class_name', $selectedClass))
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get(['id', 'family_code', 'full_name', 'class_name', 'is_b40', 'is_kwap', 'is_rmt']);

        $totalStudents = $students->count();

        $tagSummaries = collect($socialTagLabels)
            ->map(function (string $label, string $field) use ($students, $totalStudents): array {
                $count = $students->where($field, true)->count();
                $percent = $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0;

                return [
                    'field' => $field,
                    'label' => $label,
                    'hashtag' => $this->asHashtag($label),
                    'count' => $count,
                    'percent' => $percent,
                ];
            })
            ->values();

        $classBreakdown = $students
            ->groupBy(fn (Student $student): string => trim((string) ($student->class_name ?: 'Tanpa Kelas')))
            ->map(function (Collection $classStudents, string $className) use ($tagFields): array {
                $total = $classStudents->count();

                $counts = $tagFields
                    ->mapWithKeys(fn (string $field): array => [$field => $classStudents->where($field, true)->count()])
                    ->all();

                return [
                    'class_name' => $className,
                    'total_students' => $total,
                    'tag_counts' => $counts,
                ];
            })
            ->sortBy('class_name')
            ->values();

        return view('teacher.social-tags', [
            'selectedYear' => $selectedYear,
            'yearOptions' => $yearOptions,
            'selectedClass' => $selectedClass,
            'availableClasses' => $availableClasses,
            'socialTagLabels' => $socialTagLabels,
            'tagSummaries' => $tagSummaries,
            'classBreakdown' => $classBreakdown,
            'totalStudents' => $totalStudents,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function enabledSocialTagLabels(): array
    {
        $settings = SiteSetting::getMany([
            'social_tag_label_b40' => 'B40',
            'social_tag_label_kwap' => 'KWAP',
            'social_tag_label_rmt' => 'RMT',
        ]);

        return collect([
            'is_b40' => trim((string) ($settings['social_tag_label_b40'] ?? '')),
            'is_kwap' => trim((string) ($settings['social_tag_label_kwap'] ?? '')),
            'is_rmt' => trim((string) ($settings['social_tag_label_rmt'] ?? '')),
        ])
            ->filter(fn (string $label): bool => $label !== '')
            ->map(fn (string $label): string => mb_strtoupper($label))
            ->all();
    }

    private function asHashtag(string $label): string
    {
        $normalized = preg_replace('/\s+/', '', trim($label)) ?? '';
        $normalized = ltrim($normalized, '#');

        return '#'.$normalized;
    }
}