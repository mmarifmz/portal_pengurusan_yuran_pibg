<?php

namespace Database\Factories;

use App\Models\JogathonAudit;
use App\Models\JogathonCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JogathonAudit> */
class JogathonAuditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => JogathonCampaign::factory(),
            'action' => 'test.action',
            'after_values' => ['source' => 'factory'],
        ];
    }
}
