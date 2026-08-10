<?php

namespace Database\Factories;

use App\Models\JogathonCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<JogathonCampaign> */
class JogathonCampaignFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Jogathon Digital '.fake()->unique()->year();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'status' => JogathonCampaign::STATUS_DRAFT,
            'default_target_amount_sen' => 50_000,
            'default_target_distance_cm' => 500_000,
            'show_class_publicly' => false,
            'allow_public_indexing' => false,
            'allow_unspecified_cause' => false,
            'year_to_tahap' => config('jogathon.year_to_tahap'),
        ];
    }
}
