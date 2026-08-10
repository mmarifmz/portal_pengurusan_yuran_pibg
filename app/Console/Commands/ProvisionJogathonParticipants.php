<?php

namespace App\Console\Commands;

use App\Models\JogathonCampaign;
use App\Services\JogathonParticipantProvisioningService;
use Illuminate\Console\Command;

class ProvisionJogathonParticipants extends Command
{
    protected $signature = 'jogathon:provision-participants {campaign : Campaign ID or slug} {--dry-run : Preview without writing}';

    protected $description = 'Idempotently provision active students into a Jogathon campaign';

    public function handle(JogathonParticipantProvisioningService $service): int
    {
        $value = (string) $this->argument('campaign');
        $campaign = JogathonCampaign::query()
            ->where('slug', $value)
            ->when(ctype_digit($value), fn ($query) => $query->orWhereKey((int) $value))
            ->first();

        if (! $campaign) {
            $this->error('Jogathon campaign not found.');

            return self::FAILURE;
        }

        $result = $this->option('dry-run') ? $service->preview($campaign) : $service->provision($campaign);

        $this->table(['Metric', 'Count'], collect($result)->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all());

        return self::SUCCESS;
    }
}
