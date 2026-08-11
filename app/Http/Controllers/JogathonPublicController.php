<?php

namespace App\Http\Controllers;

use App\Models\JogathonCampaign;
use App\Models\JogathonParticipant;
use App\Services\JogathonParticipantVisitTracker;
use App\Services\JogathonPublicProgressService;
use App\Services\QrCodeImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class JogathonPublicController extends Controller
{
    public function home(Request $request, JogathonPublicProgressService $progressService): View
    {
        $campaign = JogathonCampaign::query()
            ->whereNull('archived_at')
            ->whereIn('status', [
                JogathonCampaign::STATUS_SCHEDULED,
                JogathonCampaign::STATUS_ACTIVE,
                JogathonCampaign::STATUS_COMPLETED,
            ])
            ->latest('id')
            ->first();

        $campaign ??= JogathonCampaign::query()
            ->whereNull('archived_at')
            ->latest('id')
            ->first();

        if (! $campaign) {
            return $this->renderConfiguredProgramLanding($request);
        }

        return $this->renderCampaignLanding($request, $campaign, $progressService);
    }

    public function campaign(
        Request $request,
        JogathonCampaign $jogathonCampaign,
        JogathonPublicProgressService $progressService,
    ): View {
        $this->ensureCampaignIsPublic($jogathonCampaign);

        return $this->renderCampaignLanding($request, $jogathonCampaign, $progressService);
    }

    public function searchParticipant(Request $request, JogathonCampaign $jogathonCampaign): RedirectResponse
    {
        $this->ensureCampaignIsPublic($jogathonCampaign);

        $request->merge([
            'physical_card_number' => JogathonParticipant::normalizePhysicalCardNumber($request->input('physical_card_number')),
        ]);

        $validated = $request->validate([
            'physical_card_number' => ['required', 'string', 'regex:/^ssp-[0-9]{4,8}$/'],
        ]);

        $cardNumber = (string) $validated['physical_card_number'];

        $participant = JogathonParticipant::query()
            ->select($this->participantSearchColumns())
            ->where('campaign_id', $jogathonCampaign->id)
            ->where('is_eligible', true)
            ->where('is_published', true)
            ->where('participation_opt_out', false)
            ->whereNull('withdrawn_at')
            ->where('physical_card_number', $cardNumber)
            ->first();

        if (! $participant) {
            return redirect()
                ->route('jogathon.public.campaigns.show', $jogathonCampaign)
                ->withErrors([
                    'physical_card_number' => 'Nombor kad peserta tidak ditemui. Sila semak nombor pada kad fizikal.',
                ]);
        }

        return redirect()->route('jogathon.public.participants.donations.create', [
            $jogathonCampaign,
            $participant->publicUrlIdentifier(),
        ]);
    }

    private function renderCampaignLanding(
        Request $request,
        JogathonCampaign $jogathonCampaign,
        JogathonPublicProgressService $progressService,
    ): View {
        $search = trim((string) $request->query('q', ''));
        $publicParticipants = fn () => JogathonParticipant::query()
            ->select($this->participantDirectoryColumns())
            ->withSum([
                'contributions as collected_amount_sen' => fn ($query) => $query->confirmed(),
            ], 'amount_sen')
            ->where('campaign_id', $jogathonCampaign->id)
            ->where('is_eligible', true)
            ->where('is_published', true)
            ->where('participation_opt_out', false)
            ->whereNull('withdrawn_at')
            ->when($search !== '', fn ($query) => $query->where('public_display_name', 'like', '%'.$search.'%'));

        $participants = $publicParticipants()
            ->orderBy('public_display_name')
            ->paginate(24)
            ->withQueryString();

        $leaderboard = $publicParticipants()
            ->orderByDesc('collected_amount_sen')
            ->orderBy('public_display_name')
            ->limit(10)
            ->get();

        $classDirectory = $publicParticipants()
            ->orderBy('class_name_snapshot')
            ->orderByDesc('collected_amount_sen')
            ->orderBy('public_display_name')
            ->get()
            ->groupBy(fn (JogathonParticipant $participant): string => filled($participant->class_name_snapshot)
                ? $participant->class_name_snapshot
                : 'Kelas belum ditetapkan');

        return view('jogathon.public.campaign', [
            'campaign' => $jogathonCampaign,
            'participants' => $participants,
            'leaderboard' => $leaderboard,
            'classDirectory' => $classDirectory,
            'summary' => $progressService->forCampaign($jogathonCampaign),
            'search' => $search,
        ]);
    }

    private function renderConfiguredProgramLanding(Request $request): View
    {
        $campaign = new JogathonCampaign([
            'name' => 'Larian Sihat Jogathon 2026',
            'slug' => 'jogathon-digital-2026',
            'description' => 'Kad kutipan digital Jogathon SK Sri Petaling untuk mengumpul dana penyelenggaraan, prasarana sekolah dan tabung kebajikan murid.',
            'status' => JogathonCampaign::STATUS_ACTIVE,
            'show_class_publicly' => true,
            'allow_public_indexing' => false,
        ]);

        $causeTotals = collect(config('jogathon.initial_causes'))
            ->values()
            ->map(function (array $cause, int $index): array {
                return [
                    'id' => $index + 1,
                    'name' => $cause['name'],
                    'description' => null,
                    'target_amount_sen' => (int) $cause['target_amount_sen'],
                    'amount_sen' => 0,
                    'progress_percent' => 0.0,
                    'visual_percent' => 0.0,
                ];
            });

        $targetAmountSen = max(0, (int) $causeTotals->sum('target_amount_sen'));

        return view('jogathon.public.campaign', [
            'campaign' => $campaign,
            'participants' => new LengthAwarePaginator([], 0, 24, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]),
            'leaderboard' => new Collection,
            'classDirectory' => new Collection,
            'summary' => [
                'amount_sen' => 0,
                'distance_cm' => 0,
                'target_amount_sen' => $targetAmountSen,
                'remaining_amount_sen' => $targetAmountSen,
                'progress_percent' => 0.0,
                'visual_percent' => 0.0,
                'participant_count' => 0,
                'cause_totals' => $causeTotals,
            ],
            'search' => trim((string) $request->query('q', '')),
        ]);
    }

    public function participant(
        Request $request,
        JogathonCampaign $jogathonCampaign,
        string $publicSlug,
        JogathonPublicProgressService $progressService,
        JogathonParticipantVisitTracker $visitTracker,
    ): View {
        $this->ensureCampaignIsPublic($jogathonCampaign);

        $participant = $this->publicParticipantByIdentifier($jogathonCampaign, $publicSlug);

        abort_unless($participant->isPubliclyVisible(), 404);
        $visitTracker->record($request, $participant, 'campaign_participant_url');

        $activeCauses = $jogathonCampaign->causes()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->get(['id', 'name', 'description', 'target_amount_sen']);

        return view('jogathon.public.participant', [
            'campaign' => $jogathonCampaign,
            'participant' => $participant,
            'progress' => $progressService->forParticipant($participant),
            'activeCauses' => $activeCauses,
        ]);
    }

    public function participantByPhysicalCard(
        Request $request,
        string $physicalCardNumber,
        JogathonPublicProgressService $progressService,
        JogathonParticipantVisitTracker $visitTracker,
    ): View {
        $cardNumber = JogathonParticipant::normalizePhysicalCardNumber($physicalCardNumber);
        abort_unless($cardNumber !== null && JogathonParticipant::hasPhysicalCardNumberColumn(), 404);

        $participant = JogathonParticipant::query()
            ->with(['campaign', 'student:id,full_name'])
            ->where('physical_card_number', $cardNumber)
            ->firstOrFail($this->participantPublicColumns());

        $campaign = $participant->campaign;
        abort_unless($campaign instanceof JogathonCampaign, 404);
        $this->ensureCampaignIsPublic($campaign);
        abort_unless($participant->isPubliclyVisible(), 404);

        $visitTracker->record($request, $participant, 'physical_card_short_url');

        $activeCauses = $campaign->causes()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->get(['id', 'name', 'description', 'target_amount_sen']);

        return view('jogathon.public.participant', [
            'campaign' => $campaign,
            'participant' => $participant,
            'progress' => $progressService->forParticipant($participant),
            'activeCauses' => $activeCauses,
        ]);
    }

    public function qr(
        JogathonCampaign $jogathonCampaign,
        string $publicSlug,
        QrCodeImageService $qrCodeImageService,
    ): Response {
        $this->ensureCampaignIsPublic($jogathonCampaign);

        $participant = $this->publicParticipantByIdentifier($jogathonCampaign, $publicSlug);

        abort_unless($participant->isPubliclyVisible(), 404);

        $baseUrl = $participant->publicShortUrl()
            ?: route('jogathon.public.participants.show', [$jogathonCampaign, $participant->publicUrlIdentifier()]);
        $url = $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').'src=qr';

        return response($qrCodeImageService->png($url, 480), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function ensureCampaignIsPublic(JogathonCampaign $campaign): void
    {
        abort_unless($campaign->isPubliclyAvailable(), 404);
    }

    private function publicParticipantByIdentifier(JogathonCampaign $campaign, string $identifier): JogathonParticipant
    {
        $normalizedCardNumber = JogathonParticipant::normalizePhysicalCardNumber($identifier);

        return JogathonParticipant::query()
            ->with('student:id,full_name')
            ->where('campaign_id', $campaign->id)
            ->where(function ($query) use ($identifier, $normalizedCardNumber): void {
                $query->where('public_slug', $identifier);

                if ($normalizedCardNumber !== null && JogathonParticipant::hasPhysicalCardNumberColumn()) {
                    $query->orWhere('physical_card_number', $normalizedCardNumber);
                }
            })
            ->firstOrFail($this->participantPublicColumns());
    }

    /**
     * @return array<int, string>
     */
    private function participantPublicColumns(): array
    {
        $columns = [
            'id',
            'campaign_id',
            'student_id',
            'public_slug',
            'public_display_name',
            'class_name_snapshot',
            'target_amount_sen',
            'target_distance_cm',
            'is_eligible',
            'is_published',
            'participation_opt_out',
            'withdrawn_at',
        ];

        if (JogathonParticipant::hasPhysicalCardNumberColumn()) {
            $columns[] = 'physical_card_number';
        }

        return $columns;
    }

    /**
     * @return array<int, string>
     */
    private function participantSearchColumns(): array
    {
        $columns = [
            'id',
            'campaign_id',
            'public_slug',
            'public_display_name',
            'is_eligible',
            'is_published',
            'participation_opt_out',
            'withdrawn_at',
        ];

        if (JogathonParticipant::hasPhysicalCardNumberColumn()) {
            $columns[] = 'physical_card_number';
        }

        return $columns;
    }

    /**
     * @return array<int, string>
     */
    private function participantDirectoryColumns(): array
    {
        $columns = [
            'id',
            'public_slug',
            'public_display_name',
            'class_name_snapshot',
            'target_amount_sen',
            'target_distance_cm',
        ];

        if (JogathonParticipant::hasPhysicalCardNumberColumn()) {
            $columns[] = 'physical_card_number';
        }

        return $columns;
    }
}
