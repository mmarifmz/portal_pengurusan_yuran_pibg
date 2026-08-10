<?php

namespace Database\Factories;

use App\Models\JogathonCampaign;
use App\Models\JogathonCause;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JogathonCause> */
class JogathonCauseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => JogathonCampaign::factory(),
            'name' => fake()->unique()->sentence(5),
            'target_amount_sen' => fake()->numberBetween(1_000_000, 5_000_000),
            'sort_order' => 1,
            'is_active' => true,
        ];
    }
}
