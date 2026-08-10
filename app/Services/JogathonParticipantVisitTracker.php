<?php

namespace App\Services;

use App\Models\JogathonParticipant;
use App\Models\JogathonParticipantVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class JogathonParticipantVisitTracker
{
    public function record(Request $request, JogathonParticipant $participant, string $accessPoint): void
    {
        if (! $this->visitTableExists()) {
            return;
        }

        $sourceData = $this->sourceData($request);

        JogathonParticipantVisit::query()->create([
            'campaign_id' => $participant->campaign_id,
            'participant_id' => $participant->id,
            'source' => $sourceData['source'],
            'channel' => $sourceData['channel'],
            'access_point' => $accessPoint,
            'url' => Str::limit($request->fullUrl(), 500, ''),
            'referrer' => $request->headers->has('referer')
                ? Str::limit((string) $request->headers->get('referer'), 500, '')
                : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'ip_hash' => $this->ipHash($request),
            'metadata' => [
                'src' => $request->query('src'),
                'utm_source' => $request->query('utm_source'),
                'utm_medium' => $request->query('utm_medium'),
                'utm_campaign' => $request->query('utm_campaign'),
            ],
            'occurred_at' => now(),
        ]);
    }

    private function visitTableExists(): bool
    {
        try {
            return Schema::hasTable('jogathon_participant_visits');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{source: string, channel: string|null}
     */
    private function sourceData(Request $request): array
    {
        $src = mb_strtolower(trim((string) ($request->query('src') ?: $request->query('utm_source'))));

        if ($src === 'qr') {
            return ['source' => 'qr', 'channel' => 'qr'];
        }

        if (in_array($src, ['whatsapp', 'facebook', 'instagram', 'telegram', 'x', 'twitter', 'tiktok', 'copy', 'social'], true)) {
            return ['source' => $src === 'copy' ? 'direct_link' : 'social', 'channel' => $src];
        }

        $referrer = mb_strtolower((string) $request->headers->get('referer', ''));
        foreach (['whatsapp', 'facebook', 'instagram', 'telegram', 'tiktok', 'twitter', 't.co'] as $socialHost) {
            if (str_contains($referrer, $socialHost)) {
                return ['source' => 'social', 'channel' => $socialHost];
            }
        }

        if ($referrer !== '') {
            return ['source' => 'referral', 'channel' => parse_url($referrer, PHP_URL_HOST) ?: null];
        }

        return ['source' => 'direct_link', 'channel' => null];
    }

    private function ipHash(Request $request): ?string
    {
        $ip = $request->ip();

        if (! $ip) {
            return null;
        }

        $key = config('app.key') ?: config('app.name', 'vjogathon');

        return hash_hmac('sha256', $ip, (string) $key);
    }
}
