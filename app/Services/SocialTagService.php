<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\SiteSetting;
use App\Models\SocialTag;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SocialTagService
{
    /**
     * @return Collection<int, SocialTag>
     */
    public function activeTags(): Collection
    {
        return SocialTag::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, SocialTag>
     */
    public function allTags(): Collection
    {
        return SocialTag::query()
            ->withCount('familyBillings')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function legacyTagLabels(): array
    {
        $settings = SiteSetting::getMany([
            'social_tag_label_b40' => 'B40',
            'social_tag_label_kwap' => 'KWAP',
            'social_tag_label_rmt' => 'RMT',
        ]);

        return [
            'is_b40' => trim((string) ($settings['social_tag_label_b40'] ?? 'B40')) ?: 'B40',
            'is_kwap' => trim((string) ($settings['social_tag_label_kwap'] ?? 'KWAP')) ?: 'KWAP',
            'is_rmt' => trim((string) ($settings['social_tag_label_rmt'] ?? 'RMT')) ?: 'RMT',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function activeTagNames(): array
    {
        return $this->activeTags()
            ->pluck('name')
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function tagFilterOptions(): array
    {
        return $this->activeTags()
            ->mapWithKeys(fn (SocialTag $tag): array => [$tag->slug => $tag->name])
            ->all();
    }

    public function resolveFilterKey(string $rawFilterKey): string
    {
        $filterKey = trim($rawFilterKey);
        if ($filterKey === '') {
            return '';
        }

        $tagBySlug = $this->activeTags()->firstWhere('slug', $filterKey);
        if ($tagBySlug) {
            return (string) $tagBySlug->slug;
        }

        $legacyLabel = $this->legacyTagLabels()[$filterKey] ?? null;
        if ($legacyLabel !== null) {
            $matched = $this->findByName($legacyLabel);

            return $matched?->slug ?? $filterKey;
        }

        $matchedByName = $this->findByName($filterKey);

        return $matchedByName?->slug ?? $filterKey;
    }

    public function resolveFilterName(string $rawFilterKey): string
    {
        $filterKey = trim($rawFilterKey);
        if ($filterKey === '') {
            return '';
        }

        $tagBySlug = $this->activeTags()->firstWhere('slug', $filterKey);
        if ($tagBySlug) {
            return (string) $tagBySlug->name;
        }

        $legacyLabel = $this->legacyTagLabels()[$filterKey] ?? null;
        if ($legacyLabel !== null) {
            return $legacyLabel;
        }

        $matchedByName = $this->findByName($filterKey);

        return $matchedByName?->name ?? $filterKey;
    }

    /**
     * @return Collection<int, string>
     */
    public function resolveFamilyTagNames(FamilyBilling $familyBilling, ?Student $contextStudent = null): Collection
    {
        $tagNames = collect();

        if ($familyBilling->exists) {
            $familyBilling->loadMissing('socialTags');
        }

        $tagNames = $tagNames
            ->merge(
                collect($familyBilling->socialTags ?? [])
                    ->pluck('name')
                    ->map(fn ($name): string => trim((string) $name))
                    ->filter()
            )
            ->merge($this->extractTagNames((string) ($familyBilling->social_tag ?? '')));

        if ($tagNames->isEmpty()) {
            $tagNames = $tagNames->merge($this->resolveLegacyStudentTagNames($familyBilling, $contextStudent));
        }

        $normalized = [];

        return $tagNames
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->filter(function (string $name) use (&$normalized): bool {
                $key = $this->normalizeTagName($name);

                if ($key === '' || in_array($key, $normalized, true)) {
                    return false;
                }

                $normalized[] = $key;

                return true;
            })
            ->values();
    }

    public function familyMatchesFilter(?FamilyBilling $familyBilling, ?Student $student, string $filterKey): bool
    {
        if (trim($filterKey) === '') {
            return false;
        }

        $requiredName = $this->resolveFilterName($filterKey);
        if ($requiredName === '') {
            return false;
        }

        $required = $this->normalizeTagName($requiredName);

        if (! $familyBilling) {
            return $this->resolveLegacyStudentTagNames(
                new FamilyBilling([
                    'family_code' => $student?->family_code,
                    'billing_year' => $student?->billing_year,
                ]),
                $student
            )->contains(fn (string $name): bool => $this->normalizeTagName($name) === $required);
        }

        return $this->resolveFamilyTagNames($familyBilling, $student)
            ->contains(fn (string $name): bool => $this->normalizeTagName($name) === $required);
    }

    public function findByName(string $name): ?SocialTag
    {
        $normalized = $this->normalizeTagName($name);

        if ($normalized === '') {
            return null;
        }

        return SocialTag::query()
            ->get()
            ->first(fn (SocialTag $tag): bool => $this->normalizeTagName((string) $tag->name) === $normalized);
    }

    public function findOrCreateByName(string $name, ?int $actorId = null): ?SocialTag
    {
        $normalizedName = $this->sanitizeName($name);

        if ($normalizedName === '') {
            return null;
        }

        $existing = $this->findByName($normalizedName);
        if ($existing) {
            return $existing;
        }

        return SocialTag::query()->create([
            'name' => $normalizedName,
            'slug' => $this->generateUniqueSlug($normalizedName),
            'is_active' => true,
            'sort_order' => 0,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    public function generateUniqueSlug(string $name, ?SocialTag $ignoreTag = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'social-tag';
        $slug = $base;
        $suffix = 2;

        while (
            SocialTag::query()
                ->when($ignoreTag, fn ($query) => $query->whereKeyNot($ignoreTag->id))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function syncFamilyPrimarySocialTag(FamilyBilling $familyBilling): void
    {
        $primaryTag = $this->resolveFamilyTagNames($familyBilling)->first();
        $familyBilling->forceFill([
            'social_tag' => $primaryTag ?: null,
        ])->save();
    }

    public function mirrorLegacyStudentTag(FamilyBilling $familyBilling, SocialTag $socialTag): void
    {
        $legacyField = $this->legacyFieldForTag($socialTag);

        if (! is_string($legacyField)) {
            return;
        }

        Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->where('billing_year', (int) $familyBilling->billing_year)
            ->update([
                $legacyField => true,
                'updated_at' => now(),
            ]);
    }

    public function legacyFieldForTag(SocialTag $socialTag): ?string
    {
        $legacyField = collect($this->legacyTagLabels())
            ->search(fn (string $label): bool => $this->normalizeTagName($label) === $this->normalizeTagName((string) $socialTag->name));

        return is_string($legacyField) ? $legacyField : null;
    }

    /**
     * @return Collection<int, string>
     */
    private function extractTagNames(string $rawValue): Collection
    {
        return collect(preg_split('/[,;]+/', $rawValue) ?: [])
            ->map(fn ($value): string => $this->sanitizeName((string) $value))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function resolveLegacyStudentTagNames(FamilyBilling $familyBilling, ?Student $contextStudent = null): Collection
    {
        $students = $contextStudent
            ? collect([$contextStudent])
            : Student::query()
                ->where('family_code', $familyBilling->family_code)
                ->where('billing_year', (int) $familyBilling->billing_year)
                ->get(['is_b40', 'is_kwap', 'is_rmt']);

        return collect($this->legacyTagLabels())
            ->filter(function (string $label, string $field) use ($students): bool {
                return $students->contains(fn ($student): bool => (bool) data_get($student, $field));
            })
            ->values();
    }

    private function sanitizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    private function normalizeTagName(string $name): string
    {
        return mb_strtoupper($this->sanitizeName($name));
    }
}
