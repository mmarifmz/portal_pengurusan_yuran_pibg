<?php

namespace App\Services;

use App\Models\JogathonCampaign;
use App\Models\JogathonContribution;
use App\Models\JogathonParticipant;

class JogathonPublicProgressService
{
    /** @return array<string, mixed> */
    public function forParticipant(JogathonParticipant $participant): array
    {
        $confirmed = JogathonContribution::query()
            ->confirmed()
            ->where('jogathon_contributions.campaign_id', $participant->campaign_id)
            ->where('jogathon_contributions.participant_id', $participant->id);

        $amountSen = max(0, (int) (clone $confirmed)->sum('amount_sen'));
        $distanceCm = max(0, (int) (clone $confirmed)->sum('distance_cm'));
        $targetAmountSen = max(1, (int) $participant->target_amount_sen);
        $targetDistanceCm = max(1, (int) $participant->target_distance_cm);

        $sourceTotals = (clone $confirmed)
            ->selectRaw('source, SUM(amount_sen) as amount_sen, SUM(distance_cm) as distance_cm')
            ->groupBy('source')
            ->get()
            ->keyBy('source');

        $causeTotals = (clone $confirmed)
            ->join('jogathon_causes', 'jogathon_causes.id', '=', 'jogathon_contributions.cause_id')
            ->selectRaw('jogathon_causes.id, jogathon_causes.name, SUM(jogathon_contributions.amount_sen) as amount_sen')
            ->groupBy('jogathon_causes.id', 'jogathon_causes.name')
            ->orderByDesc('amount_sen')
            ->get();

        $recentDonors = (clone $confirmed)
            ->with('cause:id,name')
            ->latest('finalised_at')
            ->latest('id')
            ->limit(8)
            ->get([
                'id',
                'cause_id',
                'amount_sen',
                'distance_cm',
                'donor_display_name',
                'is_anonymous_public',
                'encouragement_message',
                'is_message_approved',
                'finalised_at',
                'received_at',
            ])
            ->map(fn (JogathonContribution $contribution): array => [
                'display_name' => $contribution->is_anonymous_public || blank($contribution->donor_display_name)
                    ? 'Tanpa Nama'
                    : $contribution->donor_display_name,
                'amount_sen' => $contribution->amount_sen,
                'distance_cm' => $contribution->distance_cm,
                'cause_name' => $contribution->cause?->name,
                'message' => $contribution->is_message_approved ? $contribution->encouragement_message : null,
                'received_at' => $contribution->finalised_at ?? $contribution->received_at,
            ]);

        return [
            'amount_sen' => $amountSen,
            'distance_cm' => $distanceCm,
            'target_amount_sen' => $targetAmountSen,
            'target_distance_cm' => $targetDistanceCm,
            'remaining_amount_sen' => max(0, $targetAmountSen - $amountSen),
            'remaining_distance_cm' => max(0, $targetDistanceCm - $distanceCm),
            'progress_percent' => round(($amountSen / $targetAmountSen) * 100, 1),
            'visual_percent' => min(100, max(0, round(($amountSen / $targetAmountSen) * 100, 1))),
            'has_reached_target' => $amountSen >= $targetAmountSen,
            'online_amount_sen' => max(0, (int) ($sourceTotals->get(JogathonContribution::SOURCE_ONLINE)?->amount_sen ?? 0)),
            'physical_amount_sen' => max(0, (int) ($sourceTotals->get(JogathonContribution::SOURCE_PHYSICAL_CARD)?->amount_sen ?? 0)),
            'cause_totals' => $causeTotals,
            'recent_donors' => $recentDonors,
        ];
    }

    /** @return array<string, mixed> */
    public function forCampaign(JogathonCampaign $campaign): array
    {
        $confirmed = JogathonContribution::query()
            ->confirmed()
            ->where('jogathon_contributions.campaign_id', $campaign->id);

        $amountSen = max(0, (int) (clone $confirmed)->sum('amount_sen'));
        $distanceCm = max(0, (int) (clone $confirmed)->sum('distance_cm'));
        $activeCauses = $campaign->causes()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->get(['id', 'name', 'description', 'target_amount_sen', 'sort_order']);

        $targetAmountSen = max(0, (int) $activeCauses->sum('target_amount_sen'));
        $progressPercent = $targetAmountSen > 0 ? round(($amountSen / $targetAmountSen) * 100, 1) : 0.0;

        $causeTotals = $activeCauses
            ->map(function ($cause) use ($confirmed): array {
                $collected = max(0, (int) (clone $confirmed)->where('cause_id', $cause->id)->sum('amount_sen'));
                $target = max(1, (int) $cause->target_amount_sen);

                return [
                    'id' => $cause->id,
                    'name' => $cause->name,
                    'description' => $cause->description,
                    'target_amount_sen' => $target,
                    'amount_sen' => $collected,
                    'progress_percent' => round(($collected / $target) * 100, 1),
                    'visual_percent' => min(100, round(($collected / $target) * 100, 1)),
                ];
            });

        return [
            'amount_sen' => $amountSen,
            'distance_cm' => $distanceCm,
            'target_amount_sen' => $targetAmountSen,
            'remaining_amount_sen' => max(0, $targetAmountSen - $amountSen),
            'progress_percent' => $progressPercent,
            'visual_percent' => min(100, max(0, $progressPercent)),
            'participant_count' => $campaign->participants()
                ->where('is_eligible', true)
                ->where('is_published', true)
                ->where('participation_opt_out', false)
                ->whereNull('withdrawn_at')
                ->count(),
            'cause_totals' => $causeTotals,
        ];
    }
}
