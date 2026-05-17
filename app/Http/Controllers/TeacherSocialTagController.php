<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\PaymentCampaignSetting;
use App\Models\SocialTag;
use App\Models\Student;
use App\Services\SocialTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TeacherSocialTagController extends Controller
{
    public function __construct(private readonly SocialTagService $socialTagService)
    {
    }

    public function index(Request $request): View
    {
        // Debugbar can exhaust memory on this analytics-heavy page while collecting
        // the rendered payload, which results in a blank response for signed-in users.
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

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

        $tagFilters = $this->socialTagService->tagFilterOptions();
        $selectedTagFilter = $this->socialTagService->resolveFilterKey(trim((string) $request->query('tag_filter', 'all')));
        if ($selectedTagFilter !== 'all' && ! array_key_exists($selectedTagFilter, $tagFilters)) {
            $selectedTagFilter = 'all';
        }

        $students = Student::query()
            ->where('billing_year', $selectedYear)
            ->when($selectedClass !== 'all', fn ($query) => $query->where('class_name', $selectedClass))
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get(['id', 'family_code', 'full_name', 'class_name', 'billing_year', 'is_b40', 'is_kwap', 'is_rmt']);

        $familyBillingsByCode = FamilyBilling::query()
            ->where('billing_year', $selectedYear)
            ->whereIn('family_code', $students->pluck('family_code')->filter()->unique()->values())
            ->with('socialTags')
            ->get()
            ->keyBy(fn (FamilyBilling $billing): string => (string) $billing->family_code);

        $totalStudents = $students->count();

        $tagSummaries = collect($tagFilters)
            ->map(function (string $label, string $filterKey) use ($students, $familyBillingsByCode, $totalStudents): array {
                $count = $students->filter(
                    fn (Student $student): bool => $this->socialTagService->familyMatchesFilter(
                        $familyBillingsByCode->get((string) $student->family_code),
                        $student,
                        $filterKey
                    )
                )->count();
                $percent = $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0;

                return [
                    'key' => $filterKey,
                    'label' => $label,
                    'hashtag' => $this->asHashtag($label),
                    'count' => $count,
                    'percent' => $percent,
                ];
            })
            ->values();

        $classBreakdown = $students
            ->groupBy(fn (Student $student): string => trim((string) ($student->class_name ?: 'Tanpa Kelas')))
            ->map(function (Collection $classStudents, string $className) use ($tagFilters, $familyBillingsByCode): array {
                $total = $classStudents->count();
                $counts = collect($tagFilters)
                    ->mapWithKeys(function (string $label, string $filterKey) use ($classStudents, $familyBillingsByCode): array {
                        $count = $classStudents->filter(
                            fn (Student $student): bool => $this->socialTagService->familyMatchesFilter(
                                $familyBillingsByCode->get((string) $student->family_code),
                                $student,
                                $filterKey
                            )
                        )->count();

                        return [$filterKey => $count];
                    })
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
                ->filter(
                    fn (Student $student): bool => $this->socialTagService->familyMatchesFilter(
                        $familyBillingsByCode->get((string) $student->family_code),
                        $student,
                        $selectedTagFilter
                    )
                )
                ->values();
        }

        $selectedTagSummary = $selectedTagFilter === 'all'
            ? null
            : $tagSummaries->firstWhere('key', $selectedTagFilter);

        return view('teacher.social-tags', [
            'selectedYear' => $selectedYear,
            'yearOptions' => $yearOptions,
            'selectedClass' => $selectedClass,
            'availableClasses' => $availableClasses,
            'socialTags' => $this->socialTagService->allTags(),
            'tagSummaries' => $tagSummaries,
            'classBreakdown' => $classBreakdown,
            'totalStudents' => $totalStudents,
            'selectedTagFilter' => $selectedTagFilter,
            'selectedTagSummary' => $selectedTagSummary,
            'filteredTagStudents' => $filteredTagStudents,
        ]);
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:social_tags,name'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        SocialTag::query()->create([
            'name' => trim((string) $validated['name']),
            'slug' => $this->socialTagService->generateUniqueSlug((string) $validated['name']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('teacher.social-tags.index')
            ->with('status', 'Tag sosial baharu berjaya ditambah.');
    }

    public function updateTag(Request $request, SocialTag $socialTag): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:social_tags,name,'.$socialTag->id],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $socialTag->fill([
            'name' => trim((string) $validated['name']),
            'slug' => $this->socialTagService->generateUniqueSlug((string) $validated['name'], $socialTag),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'updated_by' => $request->user()?->id,
        ])->save();

        return redirect()
            ->route('teacher.social-tags.index')
            ->with('status', 'Tag sosial berjaya dikemas kini.');
    }

    public function destroyTag(SocialTag $socialTag): RedirectResponse
    {
        $assignedFamiliesCount = $socialTag->familyBillings()->count();
        $campaignUsageCount = PaymentCampaignSetting::query()
            ->where('split_2_social_tag_id', $socialTag->id)
            ->orWhere('split_3_social_tag_id', $socialTag->id)
            ->count();

        if ($assignedFamiliesCount > 0 || $campaignUsageCount > 0) {
            return redirect()
                ->route('teacher.social-tags.index')
                ->withErrors([
                    'social_tag_delete' => 'Tag sosial ini masih digunakan oleh family atau kempen bayaran. Nyahaktifkan dahulu sebelum padam.',
                ]);
        }

        $socialTag->delete();

        return redirect()
            ->route('teacher.social-tags.index')
            ->with('status', 'Tag sosial berjaya dipadam.');
    }

    public function bulkApply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'billing_year' => ['required', 'integer'],
            'class_name' => ['nullable', 'string', 'max:120'],
            'social_tag_id' => ['nullable', 'integer', 'exists:social_tags,id'],
            'tag_field' => ['nullable', 'string'],
            'match_lines' => ['required', 'string'],
        ]);

        $selectedYear = (int) $validated['billing_year'];
        $selectedClass = trim((string) ($validated['class_name'] ?? 'all'));
        $rawLines = (string) $validated['match_lines'];
        $socialTag = $this->resolveBulkApplyTag($validated, $request);

        if (! $socialTag) {
            return redirect()
                ->route('teacher.social-tags.index', [
                    'billing_year' => $selectedYear,
                    'class_name' => $selectedClass,
                ])
                ->withErrors(['social_tag_id' => 'Tag sosial pilihan tidak sah.']);
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
        $matchedBillings = FamilyBilling::query()
            ->where('billing_year', $selectedYear)
            ->whereIn('family_code', $matchedFamilyCodes)
            ->with('socialTags')
            ->get();

        $updatedFamiliesCount = 0;
        foreach ($matchedBillings as $billing) {
            $billing->socialTags()->syncWithoutDetaching([$socialTag->id]);
            $billing->load('socialTags');
            $this->socialTagService->syncFamilyPrimarySocialTag($billing);
            $this->socialTagService->mirrorLegacyStudentTag($billing, $socialTag);
            $updatedFamiliesCount++;
        }

        $missingBillingCount = max(0, $matchedFamilyCodes->count() - $matchedBillings->count());
        $missingBillingFamilyCodes = $matchedFamilyCodes->diff(
            $matchedBillings->pluck('family_code')->map(fn ($code): string => (string) $code)
        )->values();
        $legacyField = $this->socialTagService->legacyFieldForTag($socialTag);

        if ($legacyField !== null && $missingBillingFamilyCodes->isNotEmpty()) {
            Student::query()
                ->where('billing_year', $selectedYear)
                ->whereIn('family_code', $missingBillingFamilyCodes)
                ->update([
                    $legacyField => true,
                    'updated_at' => now(),
                ]);
        }

        $lineCount = $entries->count();
        $unmatchedCount = $unmatchedEntries->count();
        $ambiguousCount = $ambiguousEntries->count();

        $status = sprintf(
            'Bulk tag #%s selesai: %d family ditag, %d family tiada bil tahun ini, %d baris tidak jumpa, %d baris bertindih.',
            ltrim($this->asHashtag((string) $socialTag->name), '#'),
            $updatedFamiliesCount,
            $missingBillingCount,
            $unmatchedCount,
            $ambiguousCount
        );

        return redirect()
            ->route('teacher.social-tags.index', [
                'billing_year' => $selectedYear,
                'class_name' => $selectedClass,
                'tag_filter' => $socialTag->slug,
            ])
            ->with('status', $status)
            ->with('bulk_tag_report', [
                'line_count' => $lineCount,
                'matched_families_count' => $updatedFamiliesCount,
                'missing_billing_count' => $missingBillingCount,
                'unmatched_count' => $unmatchedCount,
                'ambiguous_count' => $ambiguousCount,
                'unmatched_entries' => $unmatchedEntries->values()->all(),
                'ambiguous_entries' => $ambiguousEntries->values()->all(),
                'social_tag_id' => $socialTag->id,
                'tag_label' => (string) $socialTag->name,
            ]);
    }

    private function resolveBulkApplyTag(array $validated, Request $request): ?SocialTag
    {
        $socialTagId = (int) ($validated['social_tag_id'] ?? 0);
        if ($socialTagId > 0) {
            return SocialTag::query()->find($socialTagId);
        }

        $legacyField = trim((string) ($validated['tag_field'] ?? ''));
        if ($legacyField === '') {
            return null;
        }

        $legacyLabel = $this->socialTagService->legacyTagLabels()[$legacyField] ?? null;

        return $legacyLabel ? $this->socialTagService->findOrCreateByName($legacyLabel, $request->user()?->id) : null;
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
