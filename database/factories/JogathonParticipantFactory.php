<?php

namespace Database\Factories;

use App\Models\JogathonCampaign;
use App\Models\JogathonParticipant;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<JogathonParticipant> */
class JogathonParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => JogathonCampaign::factory(),
            'student_id' => fn (): int => Student::query()->create([
                'student_no' => 'TEST-'.Str::upper(Str::random(10)),
                'full_name' => fake()->name(),
                'class_name' => '1 AKASIA',
                'status' => Student::STATUS_ACTIVE,
            ])->id,
            'public_slug' => Str::slug(fake()->unique()->name()).'-'.Str::lower(Str::random(5)),
            'public_display_name' => fake()->name(),
            'class_name_snapshot' => '1 AKASIA',
            'target_amount_sen' => 50_000,
            'target_distance_cm' => 500_000,
            'is_eligible' => true,
            'is_published' => false,
            'participation_opt_out' => false,
            'enrolled_at' => now(),
        ];
    }
}
