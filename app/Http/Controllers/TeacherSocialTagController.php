<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\PaymentCampaignSetting;
use App\Models\SocialTag;
use App\Models\Student;
use App\Services\SocialTagService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TeacherSocialTagController extends Controller
{
    public function __construct(private readonly SocialTagService $socialTagService) {}

    public function index(Request $request): View
    {
        $controllerStartedAt = microtime(true);
        $queryDurationMs = 0.0;
        $measure = function (callable $callback) use (&$queryDurationMs) {
            $startedAt = microtime(true);
            $result = $callback();
            $queryDurationMs += (microtime(true) - $startedAt) * 1000;

            return $result;
        };

        // Debugbar can exhaust memory on this analytics-heavy page while collecting
        // the rendered payload, which results in a blank response for signed-in users.
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        $currentYear = (int) now()->year;

        $yearOptions = $measure(fn () => Student::query()
            ->whereNotNull('billing_year')
            ->where('billing_year', '<=', $currentYear)
            ->select('billing_year')
            ->distinct()
            ->orderByDesc('billing_year')
            ->pluck('billing_year')
            ->map(fn ($year): int => (int) $year)
            ->values());

        if ($yearOptions->isEmpty()) {
            $yearOptions = collect([$currentYear]);
        }

        $selectedYear = (int) $request->integer('billing_year', (int) $yearOptions->first());
        if (! $yearOptions->contains($selectedYear)) {
            $selectedYear = (int) $yearOptions->first();
        }

        $availableClasses = $measure(fn () => Student::query()
            ->where('billing_year', $selectedYear)
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->select('class_name')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->map(fn ($className): string => trim((string) $className))
            ->filter()
            ->values());

        $selectedClass = trim((string) $request->query('class_name', 'all'));
        if ($selectedClass !== 'all' && ! $availableClasses->contains($selectedClass)) {
            $selectedClass = 'all';
        }

        $activeTags = $measure(fn () => $this->socialTagService->activeTags());
        $tagFilters = $activeTags
            ->mapWithKeys(fn (SocialTag $tag): array => [(string) $tag->slug => (string) $tag->name])
            ->all();
        $selectedTagFilter = $this->socialTagService->resolveFilterKey(trim((string) $request->query('tag_filter', 'all')));
        if ($selectedTagFilter !== 'all' && ! array_key_exists($selectedTagFilter, $tagFilters)) {
            $selectedTagFilter = 'all';
        }

        $baseStudentsQuery = $this->socialTagStudentQuery($selectedYear, $selectedClass);
        $totalStudents = $measure(fn () => (clone $baseStudentsQuery)->count());

        $tagSummaries = collect($tagFilters)
            ->map(function (string $label, string $filterKey) use ($activeTags, $baseStudentsQuery, $measure, $totalStudents): array {
                $tag = $activeTags->firstWhere('slug', $filterKey);
                $count = $tag instanceof SocialTag
                    ? $measure(fn () => $this->applySocialTagFilter(clone $baseStudentsQuery, $tag)->count())
                    : 0;
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

        $classBreakdown = $measure(fn () => $this->classBreakdownRows($baseStudentsQuery, $activeTags));

        $filteredTagStudents = collect();
        if ($selectedTagFilter !== 'all') {
            $selectedTag = $activeTags->firstWhere('slug', $selectedTagFilter);
            if ($selectedTag instanceof SocialTag) {
                $filteredTagStudents = $measure(fn () => $this->applySocialTagFilter(clone $baseStudentsQuery, $selectedTag)
                    ->orderBy('class_name')
                    ->orderBy('full_name')
                    ->paginate(50, ['id', 'family_code', 'full_name', 'class_name', 'billing_year'], 'page')
                    ->withQueryString());
            }
        }

        $selectedTagSummary = $selectedTagFilter === 'all'
            ? null
            : $tagSummaries->firstWhere('key', $selectedTagFilter);

        Log::info('teacher_social_tags.performance', [
            'billing_year' => $selectedYear,
            'class_name' => $selectedClass,
            'tag_filter' => $selectedTagFilter,
            'total_students' => $totalStudents,
            'query_duration_ms' => round($queryDurationMs, 2),
            'controller_duration_ms' => round((microtime(true) - $controllerStartedAt) * 1000, 2),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

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

    private function socialTagStudentQuery(int $selectedYear, string $selectedClass): Builder
    {
        return Student::query()
            ->where('billing_year', $selectedYear)
            ->when($selectedClass !== 'all', fn (Builder $query) => $query->where('class_name', $selectedClass));
    }

    private function applySocialTagFilter(Builder $query, SocialTag $tag): Builder
    {
        $legacyField = $this->socialTagService->legacyFieldForTag($tag);

        return $query->where(function (Builder $tagQuery) use ($tag, $legacyField): void {
            if ($legacyField !== null) {
                $tagQuery->where($legacyField, true);
            }

            $tagQuery
                ->orWhereExists(function ($subquery) use ($tag): void {
                    $subquery
                        ->selectRaw('1')
                        ->from('family_billings')
                        ->join('family_social_tags', 'family_social_tags.family_billing_id', '=', 'family_billings.id')
                        ->whereColumn('family_billings.family_code', 'students.family_code')
                        ->whereColumn('family_billings.billing_year', 'students.billing_year')
                        ->where('family_social_tags.social_tag_id', $tag->id);
                })
                ->orWhereExists(function ($subquery) use ($tag): void {
                    $tagName = strtolower(str_replace(' ', '', trim((string) $tag->name)));

                    $subquery
                        ->selectRaw('1')
                        ->from('family_billings')
                        ->whereColumn('family_billings.family_code', 'students.family_code')
                        ->whereColumn('family_billings.billing_year', 'students.billing_year')
                        ->where(function ($legacyTagQuery) use ($tagName): void {
                            $normalizedColumn = "LOWER(REPLACE(family_billings.social_tag, ' ', ''))";

                            $legacyTagQuery
                                ->whereRaw($normalizedColumn.' = ?', [$tagName])
                                ->orWhereRaw($normalizedColumn.' LIKE ?', [$tagName.',%'])
                                ->orWhereRaw($normalizedColumn.' LIKE ?', [$tagName.';%'])
                                ->orWhereRaw($normalizedColumn.' LIKE ?', ['%,'.$tagName])
                                ->orWhereRaw($normalizedColumn.' LIKE ?', ['%;'.$tagName])
                                ->orWhereRaw($normalizedColumn.' LIKE ?', ['%,'.$tagName.',%'])
                                ->orWhereRaw($normalizedColumn.' LIKE ?', ['%,'.$tagName.';%'])
                                ->orWhereRaw($normalizedColumn.' LIKE ?', ['%;'.$tagName.',%'])
                                ->orWhereRaw($normalizedColumn.' LIKE ?', ['%;'.$tagName.';%']);
                        });
                });
        });
    }

    /**
     * @param  Collection<int, SocialTag>  $activeTags
     * @return Collection<int, array{class_name:string,total_students:int,tag_counts:array<string,int>}>
     */
    private function classBreakdownRows(Builder $baseStudentsQuery, Collection $activeTags): Collection
    {
        $classExpression = "COALESCE(NULLIF(TRIM(class_name), ''), 'Tanpa Kelas')";
        $totalsByClass = (clone $baseStudentsQuery)
            ->selectRaw($classExpression.' as class_name, COUNT(*) as total_students')
            ->groupBy(DB::raw($classExpression))
            ->orderBy('class_name')
            ->get();

        $tagCountsByClass = [];
        foreach ($activeTags as $tag) {
            $counts = $this->applySocialTagFilter(clone $baseStudentsQuery, $tag)
                ->selectRaw($classExpression.' as class_name, COUNT(*) as tagged_students')
                ->groupBy(DB::raw($classExpression))
                ->pluck('tagged_students', 'class_name');

            foreach ($counts as $className => $count) {
                $tagCountsByClass[(string) $className][(string) $tag->slug] = (int) $count;
            }
        }

        return $totalsByClass
            ->map(fn ($row): array => [
                'class_name' => (string) $row->class_name,
                'total_students' => (int) $row->total_students,
                'tag_counts' => $tagCountsByClass[(string) $row->class_name] ?? [],
            ])
            ->values();
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

        $parsedBulkInput = $this->parseBulkInput($rawLines);
        $entries = $parsedBulkInput['entries'];
        $invalidEntries = $parsedBulkInput['invalid_entries'];

        if ($entries->isEmpty()) {
            return redirect()
                ->route('teacher.social-tags.index', [
                    'billing_year' => $selectedYear,
                    'class_name' => $selectedClass,
                ])
                ->withErrors(['match_lines' => 'Tiada baris data yang boleh dipadankan.'])
                ->with('bulk_tag_report', [
                    'line_count' => $invalidEntries->count(),
                    'matched_families_count' => 0,
                    'missing_billing_count' => 0,
                    'invalid_count' => $invalidEntries->count(),
                    'duplicate_count' => 0,
                    'unmatched_count' => 0,
                    'ambiguous_count' => 0,
                    'missing_billing_family_codes' => [],
                    'invalid_entries' => $invalidEntries->values()->all(),
                    'duplicate_entries' => [],
                    'unmatched_entries' => [],
                    'ambiguous_entries' => [],
                    'social_tag_id' => $socialTag->id,
                    'tag_label' => (string) $socialTag->name,
                ]);
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
        $duplicateEntries = collect();
        $seenEntryKeys = [];

        foreach ($entries as $entry) {
            $nameToken = $entry['name_token'];
            $classToken = $entry['class_token'];

            if ($nameToken === '') {
                continue;
            }

            $entryKey = $nameToken.'|'.$classToken;
            if (array_key_exists($entryKey, $seenEntryKeys)) {
                $duplicateEntries->push($entry['raw']);

                continue;
            }

            $seenEntryKeys[$entryKey] = true;
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

        $lineCount = $entries->count() + $invalidEntries->count();
        $invalidCount = $invalidEntries->count();
        $duplicateCount = $duplicateEntries->count();
        $unmatchedCount = $unmatchedEntries->count();
        $ambiguousCount = $ambiguousEntries->count();
        $issueCount = $missingBillingCount + $invalidCount + $duplicateCount + $unmatchedCount + $ambiguousCount;

        $status = sprintf(
            'Bulk tag #%s selesai: %d family ditag, %d item perlu semakan.',
            ltrim($this->asHashtag((string) $socialTag->name), '#'),
            $updatedFamiliesCount,
            $issueCount
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
                'invalid_count' => $invalidCount,
                'duplicate_count' => $duplicateCount,
                'unmatched_count' => $unmatchedCount,
                'ambiguous_count' => $ambiguousCount,
                'missing_billing_family_codes' => $missingBillingFamilyCodes->all(),
                'invalid_entries' => $invalidEntries->values()->all(),
                'duplicate_entries' => $duplicateEntries->values()->all(),
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
     * @return array{entries: Collection<int, array{raw:string,name_token:string,class_token:string}>, invalid_entries: Collection<int, string>}
     */
    private function parseBulkInput(string $rawLines): array
    {
        $lines = preg_split('/\R/u', $rawLines) ?: [];
        $invalidEntries = collect();

        $entries = collect($lines)
            ->map(fn ($line): string => trim((string) $line))
            ->filter()
            ->map(function (string $line) use ($invalidEntries): ?array {
                $columns = $this->splitBulkColumns($line);
                if ($columns === []) {
                    $invalidEntries->push($line);

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
                    $invalidEntries->push($line);

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

        return [
            'entries' => $entries,
            'invalid_entries' => $invalidEntries->values(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function splitBulkColumns(string $line): array
    {
        $line = str_replace(['[TAB]', '[tab]'], "\t", $line);
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
