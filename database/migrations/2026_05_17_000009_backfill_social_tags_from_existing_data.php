<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('social_tags') || ! DB::getSchemaBuilder()->hasTable('family_social_tags')) {
            return;
        }

        $legacyLabels = $this->legacyLabels();
        $defaultTags = $legacyLabels;

        $defaultTags->each(function (string $name): void {
            $this->firstOrCreateTag($name);
        });

        DB::table('family_billings')
            ->select(['id', 'social_tag'])
            ->whereNotNull('social_tag')
            ->orderBy('id')
            ->chunk(200, function ($billings): void {
                foreach ($billings as $billing) {
                    $tagNames = $this->extractTagNames((string) $billing->social_tag);

                    foreach ($tagNames as $tagName) {
                        $tagId = $this->firstOrCreateTag($tagName);
                        $this->attachFamilyTag((int) $billing->id, $tagId);
                    }
                }
            });

        DB::table('students')
            ->select([
                'family_code',
                'billing_year',
                DB::raw('MAX(CASE WHEN is_b40 = 1 THEN 1 ELSE 0 END) as has_b40'),
                DB::raw('MAX(CASE WHEN is_kwap = 1 THEN 1 ELSE 0 END) as has_kwap'),
                DB::raw('MAX(CASE WHEN is_rmt = 1 THEN 1 ELSE 0 END) as has_rmt'),
            ])
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->groupBy('family_code', 'billing_year')
            ->orderBy('family_code')
            ->chunk(200, function ($rows) use ($legacyLabels): void {
                foreach ($rows as $row) {
                    $familyBillingId = DB::table('family_billings')
                        ->where('family_code', (string) $row->family_code)
                        ->where('billing_year', (int) $row->billing_year)
                        ->value('id');

                    if (! $familyBillingId) {
                        continue;
                    }

                    if ((int) $row->has_b40 === 1) {
                        $this->attachFamilyTag((int) $familyBillingId, $this->firstOrCreateTag((string) $legacyLabels->get('b40', 'B40')));
                    }

                    if ((int) $row->has_kwap === 1) {
                        $this->attachFamilyTag((int) $familyBillingId, $this->firstOrCreateTag((string) $legacyLabels->get('kwap', 'KWAP')));
                    }

                    if ((int) $row->has_rmt === 1) {
                        $this->attachFamilyTag((int) $familyBillingId, $this->firstOrCreateTag((string) $legacyLabels->get('rmt', 'RMT')));
                    }
                }
            });

        DB::table('payment_campaign_settings')
            ->select(['id', 'split_2_social_tag', 'split_3_social_tag'])
            ->orderBy('id')
            ->chunk(200, function ($settings): void {
                foreach ($settings as $setting) {
                    $updates = [];

                    if (filled($setting->split_2_social_tag)) {
                        $updates['split_2_social_tag_id'] = $this->firstOrCreateTag((string) $setting->split_2_social_tag);
                    }

                    if (filled($setting->split_3_social_tag)) {
                        $updates['split_3_social_tag_id'] = $this->firstOrCreateTag((string) $setting->split_3_social_tag);
                    }

                    if ($updates !== []) {
                        DB::table('payment_campaign_settings')
                            ->where('id', (int) $setting->id)
                            ->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
    }

    private function attachFamilyTag(int $familyBillingId, int $socialTagId): void
    {
        $exists = DB::table('family_social_tags')
            ->where('family_billing_id', $familyBillingId)
            ->where('social_tag_id', $socialTagId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('family_social_tags')->insert([
            'family_billing_id' => $familyBillingId,
            'social_tag_id' => $socialTagId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function firstOrCreateTag(string $rawName): int
    {
        $name = $this->normalizeName($rawName);
        $existingId = DB::table('social_tags')->whereRaw('UPPER(name) = ?', [mb_strtoupper($name)])->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        $slugBase = Str::slug($name);
        $slugBase = $slugBase !== '' ? $slugBase : 'social-tag';
        $slug = $slugBase;
        $suffix = 2;

        while (DB::table('social_tags')->where('slug', $slug)->exists()) {
            $slug = $slugBase.'-'.$suffix;
            $suffix++;
        }

        return (int) DB::table('social_tags')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function extractTagNames(string $rawValue): Collection
    {
        return collect(preg_split('/[,;]+/', $rawValue) ?: [])
            ->map(fn ($value): string => $this->normalizeName((string) $value))
            ->filter()
            ->unique()
            ->values();
    }

    private function normalizeName(string $rawName): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $rawName) ?? '');
    }

    /**
     * @return Collection<string, string>
     */
    private function legacyLabels(): Collection
    {
        $settings = DB::getSchemaBuilder()->hasTable('site_settings')
            ? DB::table('site_settings')
                ->whereIn('key', ['social_tag_label_b40', 'social_tag_label_kwap', 'social_tag_label_rmt'])
                ->pluck('value', 'key')
            : collect();

        return collect([
            'b40' => trim((string) ($settings['social_tag_label_b40'] ?? 'B40')) ?: 'B40',
            'kwap' => trim((string) ($settings['social_tag_label_kwap'] ?? 'KWAP')) ?: 'KWAP',
            'rmt' => trim((string) ($settings['social_tag_label_rmt'] ?? 'RMT')) ?: 'RMT',
        ]);
    }
};
