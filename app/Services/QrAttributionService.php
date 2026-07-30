<?php

namespace App\Services;

use App\Models\QrCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class QrAttributionService
{
    private const SESSION_KEY = 'qr_campaign_attribution';

    private const ATTRIBUTION_DAYS = 30;

    public function remember(Request $request, QrCampaign $campaign): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'campaign_id' => $campaign->id,
            'scanned_at' => now()->toIso8601String(),
        ]);
    }

    public function campaignId(Request $request): ?int
    {
        $attribution = $request->session()->get(self::SESSION_KEY);
        if (! is_array($attribution) || empty($attribution['campaign_id']) || empty($attribution['scanned_at'])) {
            return null;
        }

        try {
            $scannedAt = Carbon::parse((string) $attribution['scanned_at']);
        } catch (Throwable) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        if ($scannedAt->lt(now()->subDays(self::ATTRIBUTION_DAYS))) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $campaignId = (int) $attribution['campaign_id'];

        return QrCampaign::query()->whereKey($campaignId)->exists() ? $campaignId : null;
    }
}
