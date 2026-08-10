<?php

namespace Database\Factories;

use App\Models\JogathonCause;
use App\Models\JogathonContribution;
use App\Models\JogathonParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JogathonContribution> */
class JogathonContributionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'participant_id' => JogathonParticipant::factory(),
            'campaign_id' => fn (array $attributes): int => JogathonParticipant::query()->findOrFail($attributes['participant_id'])->campaign_id,
            'cause_id' => fn (array $attributes): int => JogathonCause::factory()->create(['campaign_id' => $attributes['campaign_id']])->id,
            'source' => JogathonContribution::SOURCE_ONLINE,
            'amount_sen' => 2_000,
            'distance_cm' => 20_000,
            'status' => JogathonContribution::STATUS_SUCCESSFUL,
            'donor_display_name' => fake()->firstName(),
            'is_anonymous_public' => false,
            'received_at' => now(),
            'finalised_at' => now(),
        ];
    }
}
