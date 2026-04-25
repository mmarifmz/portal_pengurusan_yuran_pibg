<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
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
        $selectedTagFilter = trim((string) $request->query('tag_filter', 'all'));
        if ($selectedTagFilter !== 'all' && ! $tagFields->contains($selectedTagFilter)) {
            $selectedTagFilter = 'all';
        }

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

        $filteredTagStudents = collect();
        if ($selectedTagFilter !== 'all') {
            $filteredTagStudents = $students
                ->filter(fn (Student $student): bool => (bool) data_get($student, $selectedTagFilter))
                ->values();
        }

        $selectedTagSummary = $selectedTagFilter === 'all'
            ? null
            : $tagSummaries
                ->firstWhere('field', $selectedTagFilter);

        return view('teacher.social-tags', [
            'selectedYear' => $selectedYear,
            'yearOptions' => $yearOptions,
            'selectedClass' => $selectedClass,
            'availableClasses' => $availableClasses,
            'socialTagLabels' => $socialTagLabels,
            'tagSummaries' => $tagSummaries,
            'classBreakdown' => $classBreakdown,
            'totalStudents' => $totalStudents,
            'selectedTagFilter' => $selectedTagFilter,
            'selectedTagSummary' => $selectedTagSummary,
            'filteredTagStudents' => $filteredTagStudents,
        ]);
    }

    public function bulkApply(Request $request): RedirectResponse
    {
        $socialTagLabels = $this->enabledSocialTagLabels();
        $allowedTagFields = collect(array_keys($socialTagLabels));

        $validated = $request->validate([
            'billing_year' => ['required', 'integer'],
            'class_name' => ['nullable', 'string', 'max:120'],
            'tag_field' => ['required', 'string'],
            'match_lines' => ['required', 'string'],
        ]);

        $selectedYear = (int) $validated['billing_year'];
        $selectedClass = trim((string) ($validated['class_name'] ?? 'all'));
        $tagField = trim((string) $validated['tag_field']);
        $rawLines = (string) $validated['match_lines'];

        if (! $allowedTagFields->contains($tagField)) {
            return redirect()
                ->route('teacher.social-tags.index', [
                    'billing_year' => $selectedYear,
                    'class_name' => $selectedClass,
                ])
                ->withErrors(['tag_field' => 'Tag pilihan tidak sah.']);
        }

        $entries = $this->parseBulkEntries($rawLines);
        if ($entries->isEmpty()) {
            return redirect()
                ->route('teacher.social-tags.index', [
                    'billing_year' => $selectedYear,
                    'class_name' => $selectedClass,
                ])
                ->withErrors(['match_lines' => 'Tiada baris data yang boleh dipadankan.']);
        }

        $students = Student::query()
            ->where('billing_year', $selectedYear)
            ->when($selectedClass !== '' && $selectedClass !== 'all', fn ($query) => $query->where('class_name', $selectedClass))
            ->get(['id', 'family_code', 'full_name', 'class_name']);

        $studentsByName = $students->groupBy(fn (Student $student): string => $this->normalizeToken((string) $student->full_name));
        $studentsByNameClass = $students->groupBy(function (Student $student): string {
            return $this->normalizeToken((string) $student->full_name).'|'.$this->normalizeToken((string) $student->class_name);
        });

        $matchedFamilyCodes = collect();
        $unmatchedEntries = collect();
        $ambiguousEntries = collect();

        foreach ($entries as $entry) {
            $nameToken = $entry['name_token'];
            $classToken = $entry['class_token'];

            if ($nameToken === '') {
                continue;
            }

            $matchedStudents = collect();

            if ($classToken !== '') {
                $matchedStudents = collect($studentsByNameClass->get($nameToken.'|'.$classToken, []));
            }

            if ($matchedStudents->isEmpty()) {
                $matchedStudents = collect($studentsByName->get($nameToken, []));
            }

            $familyCandidates = $matchedStudents
                ->pluck('family_code')
                ->map(fn ($code): string => trim((string) $code))
                ->filter()
                ->unique()
                ->values();

            if ($familyCandidates->isEmpty()) {
                $unmatchedEntries->push($entry['raw']);

                continue;
            }

            if ($familyCandidates->count() > 1) {
                $ambiguousEntries->push($entry['raw']);

                continue;
            }

            $matchedFamilyCodes->push((string) $familyCandidates->first());
        }

        $matchedFamilyCodes = $matchedFamilyCodes->unique()->values();

        $updatedStudentsCount = 0;
        if ($matchedFamilyCodes->isNotEmpty()) {
            $updatedStudentsCount = Student::query()
                ->where('billing_year', $selectedYear)
                ->whereIn('family_code', $matchedFamilyCodes)
                ->update([
                    $tagField => true,
                    'updated_at' => now(),
                ]);
        }

        $matchedFamiliesCount = $matchedFamilyCodes->count();
        $lineCount = $entries->count();
        $unmatchedCount = $unmatchedEntries->count();
        $ambiguousCount = $ambiguousEntries->count();
        $tagLabel = (string) ($socialTagLabels[$tagField] ?? $tagField);

        $status = sprintf(
            'Bulk tag #%s selesai: %d family dipadankan, %d murid dikemas kini, %d baris tidak jumpa, %d baris bertindih.',
            ltrim($this->asHashtag($tagLabel), '#'),
            $matchedFamiliesCount,
            $updatedStudentsCount,
            $unmatchedCount,
            $ambiguousCount
        );

        return redirect()
            ->route('teacher.social-tags.index', [
                'billing_year' => $selectedYear,
                'class_name' => $selectedClass,
                'tag_filter' => $tagField,
            ])
            ->with('status', $status)
            ->with('bulk_tag_report', [
                'line_count' => $lineCount,
                'matched_families_count' => $matchedFamiliesCount,
                'updated_students_count' => $updatedStudentsCount,
                'unmatched_count' => $unmatchedCount,
                'ambiguous_count' => $ambiguousCount,
                'unmatched_entries' => $unmatchedEntries->values()->all(),
                'ambiguous_entries' => $ambiguousEntries->values()->all(),
                'unmatched_preview' => $unmatchedEntries->take(8)->values()->all(),
                'ambiguous_preview' => $ambiguousEntries->take(8)->values()->all(),
                'tag_field' => $tagField,
                'tag_label' => $tagLabel,
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

    /**
     * @return Collection<int, array{raw:string,name_token:string,class_token:string}>
     */
    private function parseBulkEntries(string $rawLines): Collection
    {
        $lines = preg_split('/\R/u', $rawLines) ?: [];

        return collect($lines)
            ->map(fn ($line): string => trim((string) $line))
            ->filter()
            ->map(function (string $line): ?array {
                $columns = $this->splitBulkColumns($line);
                if ($columns === []) {
                    return null;
                }

                $name = '';
                $class = '';

                if (count($columns) >= 3 && $this->looksLikeRowNumber($columns[0])) {
                    $name = (string) ($columns[1] ?? '');
                    $class = (string) ($columns[2] ?? '');
                } elseif (count($columns) >= 2) {
                    $name = (string) ($columns[0] ?? '');
                    $class = (string) ($columns[1] ?? '');
                } else {
                    $name = (string) ($columns[0] ?? '');
                }

                $nameToken = $this->normalizeToken($name);
                if ($nameToken === '') {
                    return null;
                }

                return [
                    'raw' => $line,
                    'name_token' => $nameToken,
                    'class_token' => $this->normalizeToken($class),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function splitBulkColumns(string $line): array
    {
        $columns = [];

        if (str_contains($line, "\t")) {
            $columns = array_map('trim', explode("\t", $line));
        } elseif (str_contains($line, ',')) {
            $columns = array_map('trim', str_getcsv($line));
        } else {
            $columns = preg_split('/\s{2,}/', $line) ?: [$line];
            $columns = array_map('trim', $columns);
        }

        return array_values(array_filter($columns, fn ($value): bool => trim((string) $value) !== ''));
    }

    private function looksLikeRowNumber(string $value): bool
    {
        return (bool) preg_match('/^\d+$/', trim($value));
    }

    private function normalizeToken(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }
}
