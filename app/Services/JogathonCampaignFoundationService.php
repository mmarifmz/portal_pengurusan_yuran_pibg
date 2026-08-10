<?php

namespace App\Services;

use App\Models\JogathonAudit;
use App\Models\JogathonCampaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JogathonCampaignFoundationService
{
    public function create(array $attributes, ?User $actor = null): JogathonCampaign
    {
        return DB::transaction(function () use ($attributes, $actor): JogathonCampaign {
            $campaign = JogathonCampaign::query()->create(array_merge($attributes, [
                'slug' => $this->uniqueSlug((string) $attributes['name']),
                'created_by_user_id' => $actor?->id,
                'year_to_tahap' => $attributes['year_to_tahap'] ?? config('jogathon.year_to_tahap'),
            ]));

            foreach (config('jogathon.initial_causes', []) as $index => $cause) {
                $campaign->causes()->create([
                    'name' => $cause['name'],
                    'target_amount_sen' => $cause['target_amount_sen'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);
            }

            JogathonAudit::query()->create([
                'campaign_id' => $campaign->id,
                'auditable_type' => JogathonCampaign::class,
                'auditable_id' => $campaign->id,
                'action' => 'campaign.created',
                'after_values' => $campaign->only(['name', 'slug', 'status', 'default_target_amount_sen', 'default_target_distance_cm']),
                'actor_user_id' => $actor?->id,
            ]);

            return $campaign;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'jogathon';
        $slug = $base;
        $suffix = 2;

        while (JogathonCampaign::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
