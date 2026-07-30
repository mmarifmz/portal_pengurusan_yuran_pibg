<?php

namespace App\Http\Controllers;

use App\Models\QrCampaign;
use App\Services\QrAttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QrCampaignRedirectController extends Controller
{
    public function __construct(
        private readonly QrAttributionService $qrAttributionService,
    ) {}

    public function __invoke(Request $request, QrCampaign $qrCampaign): RedirectResponse
    {
        if (! $qrCampaign->isAvailable()) {
            throw new HttpException(410, 'Pautan QR ini tidak lagi aktif.');
        }

        $userAgent = Str::limit((string) $request->userAgent(), 500, '');
        $visitorHash = hash_hmac(
            'sha256',
            (string) $request->ip().'|'.$userAgent,
            (string) config('app.key'),
        );

        $qrCampaign->scans()->create([
            'scanned_at' => now(),
            'visitor_hash' => $visitorHash,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'referrer' => Str::limit((string) $request->headers->get('referer'), 1000, '') ?: null,
        ]);

        $this->qrAttributionService->remember($request, $qrCampaign);

        $destination = url($qrCampaign->destination_path);
        $separator = str_contains($destination, '?') ? '&' : '?';
        $destination .= $separator.http_build_query(['qr_campaign' => $qrCampaign->short_code]);

        return redirect()
            ->to($destination)
            ->withHeaders([
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
    }
}
