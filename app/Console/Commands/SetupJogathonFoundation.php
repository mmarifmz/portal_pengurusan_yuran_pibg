<?php

namespace App\Console\Commands;

use App\Models\JogathonCampaign;
use App\Services\JogathonCampaignFoundationService;
use App\Services\JogathonParticipantProvisioningService;
use Illuminate\Console\Command;

class SetupJogathonFoundation extends Command
{
    protected $signature = 'jogathon:setup-foundation
        {--name=Jogathon Digital SK Sri Petaling 2026 : Campaign name}
        {--provision : Provision active students after setup}';

    protected $description = 'Idempotently create the draft Jogathon campaign and initial causes';

    public function handle(
        JogathonCampaignFoundationService $foundationService,
        JogathonParticipantProvisioningService $provisioningService,
    ): int {
        $name = trim((string) $this->option('name'));

        if ($name === '') {
            $this->error('Campaign name is required.');

            return self::FAILURE;
        }

        $campaign = JogathonCampaign::query()->where('name', $name)->first();

        if (! $campaign) {
            $campaign = $foundationService->create([
                'name' => $name,
                'description' => 'Kempen larian maya dan kutipan dana SK Sri Petaling.',
                'status' => JogathonCampaign::STATUS_DRAFT,
                'default_target_amount_sen' => 50_000,
                'default_target_distance_cm' => 500_000,
                'show_class_publicly' => false,
                'allow_public_indexing' => false,
                'allow_unspecified_cause' => false,
            ]);
            $this->info("Draft campaign created: {$campaign->slug}");
        } else {
            $this->info("Existing campaign reused: {$campaign->slug}");
        }

        if ($this->option('provision')) {
            $result = $provisioningService->provision($campaign);
            $this->table(['Metric', 'Count'], collect($result)->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all());
        }

        return self::SUCCESS;
    }
}
