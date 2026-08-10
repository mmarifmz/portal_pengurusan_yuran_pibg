<?php

namespace App\Http\Controllers;

use App\Models\JogathonAudit;
use App\Models\JogathonCampaign;
use App\Models\JogathonParticipant;
use App\Models\JogathonParticipantVisit;
use App\Models\User;
use App\Services\JogathonCampaignFoundationService;
use App\Services\JogathonParticipantProvisioningService;
use App\Support\JogathonAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JogathonCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $campaigns = JogathonCampaign::query()
            ->withCount(['causes', 'participants', 'participants as eligible_participants_count' => fn ($query) => $query->where('is_eligible', true)])
            ->latest('id')
            ->get();

        $selectedCampaign = $request->integer('campaign') > 0
            ? $campaigns->firstWhere('id', $request->integer('campaign'))
            : $campaigns->first();

        $participants = $selectedCampaign
            ? JogathonParticipant::query()
                ->with('student:id,full_name,class_name,status')
                ->where('campaign_id', $selectedCampaign->id)
                ->orderBy('class_name_snapshot')
                ->orderBy('public_display_name')
                ->paginate(30)
                ->withQueryString()
            : null;

        $campaignClassNames = $selectedCampaign
            ? JogathonParticipant::query()
                ->where('campaign_id', $selectedCampaign->id)
                ->whereNotNull('class_name_snapshot')
                ->where('class_name_snapshot', '!=', '')
                ->distinct()
                ->orderBy('class_name_snapshot')
                ->pluck('class_name_snapshot')
                ->values()
            : collect();

        $classNames = $participants?->getCollection()->pluck('class_name_snapshot')->filter()->unique()->values() ?? collect();
        $teachersByClass = User::query()
            ->withAnyRole(['teacher', 'super_teacher'])
            ->whereIn('class_name', $classNames)
            ->get(['name', 'class_name'])
            ->groupBy(fn (User $user): string => mb_strtoupper(trim((string) $user->class_name)));

        $publishStats = $selectedCampaign
            ? [
                'eligible' => JogathonParticipant::query()
                    ->where('campaign_id', $selectedCampaign->id)
                    ->where('is_eligible', true)
                    ->where('participation_opt_out', false)
                    ->whereNull('withdrawn_at')
                    ->count(),
                'published' => JogathonParticipant::query()
                    ->where('campaign_id', $selectedCampaign->id)
                    ->where('is_published', true)
                    ->count(),
            ]
            : ['eligible' => 0, 'published' => 0];

        $visitStats = $selectedCampaign && $this->participantVisitsTableExists()
            ? JogathonParticipantVisit::query()
                ->select('source', DB::raw('count(*) as total'))
                ->where('campaign_id', $selectedCampaign->id)
                ->groupBy('source')
                ->orderByDesc('total')
                ->pluck('total', 'source')
            : collect();

        return view('system.jogathon.campaigns.index', [
            'campaigns' => $campaigns,
            'selectedCampaign' => $selectedCampaign?->load('causes'),
            'participants' => $participants,
            'campaignClassNames' => $campaignClassNames,
            'publishStats' => $publishStats,
            'visitStats' => $visitStats,
            'teachersByClass' => $teachersByClass,
            'statusOptions' => JogathonCampaign::statusOptions(),
        ]);
    }

    public function store(Request $request, JogathonCampaignFoundationService $foundationService): RedirectResponse
    {
        $validated = $this->validateCampaign($request);
        $campaign = $foundationService->create($validated, $request->user());

        return redirect()->route('system.jogathon.campaigns.index', ['campaign' => $campaign->id])
            ->with('status', 'Kempen Jogathon dicipta sebagai draf bersama lima tujuan awal.');
    }

    public function update(Request $request, JogathonCampaign $jogathonCampaign): RedirectResponse
    {
        $validated = $this->validateCampaign($request);

        DB::transaction(function () use ($jogathonCampaign, $validated, $request): void {
            $before = $jogathonCampaign->only(array_keys($validated));
            $jogathonCampaign->fill($validated);
            $jogathonCampaign->archived_at = $validated['status'] === JogathonCampaign::STATUS_ARCHIVED ? now() : null;
            $jogathonCampaign->save();

            JogathonAudit::query()->create([
                'campaign_id' => $jogathonCampaign->id,
                'auditable_type' => JogathonCampaign::class,
                'auditable_id' => $jogathonCampaign->id,
                'action' => 'campaign.updated',
                'before_values' => $before,
                'after_values' => $jogathonCampaign->only(array_keys($validated)),
                'actor_user_id' => $request->user()?->id,
            ]);
        });

        return back()->with('status', 'Tetapan kempen Jogathon dikemas kini.');
    }

    public function provision(Request $request, JogathonCampaign $jogathonCampaign, JogathonParticipantProvisioningService $service): RedirectResponse
    {
        $result = $service->provision($jogathonCampaign, $request->user());

        return redirect()->route('system.jogathon.campaigns.index', ['campaign' => $jogathonCampaign->id])
            ->with('status', sprintf(
                'Provision selesai: %d layak, %d baharu, %d disegarkan, %d ditarik balik.',
                $result['eligible'],
                $result['created'],
                $result['refreshed'],
                $result['withdrawn'],
            ));
    }

    public function publishParticipants(Request $request, JogathonCampaign $jogathonCampaign, JogathonParticipantProvisioningService $service): RedirectResponse
    {
        $validated = $request->validate([
            'class_name' => ['nullable', 'string', 'max:120'],
            'activate_campaign' => ['nullable', 'boolean'],
        ]);

        $className = filled($validated['class_name'] ?? null) ? trim((string) $validated['class_name']) : null;

        $result = DB::transaction(function () use ($className, $jogathonCampaign, $request, $service, $validated): array {
            /** @var Collection<int, JogathonParticipant> $participants */
            $participants = JogathonParticipant::query()
                ->with('student:id,full_name')
                ->where('campaign_id', $jogathonCampaign->id)
                ->where('is_eligible', true)
                ->where('participation_opt_out', false)
                ->whereNull('withdrawn_at')
                ->when($className !== null, fn ($query) => $query->where('class_name_snapshot', $className))
                ->orderBy('class_name_snapshot')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $beforeCampaign = $jogathonCampaign->only(['status', 'show_class_publicly', 'allow_public_indexing']);

            if ((bool) ($validated['activate_campaign'] ?? false)) {
                $jogathonCampaign->update([
                    'status' => JogathonCampaign::STATUS_ACTIVE,
                    'allow_public_indexing' => false,
                ]);
            }

            $publishResult = $service->publishSafely($participants);

            JogathonAudit::query()->create([
                'campaign_id' => $jogathonCampaign->id,
                'auditable_type' => JogathonCampaign::class,
                'auditable_id' => $jogathonCampaign->id,
                'action' => 'participants.safe_published',
                'before_values' => [
                    'campaign' => $beforeCampaign,
                ],
                'after_values' => [
                    'campaign' => $jogathonCampaign->fresh()->only(array_keys($beforeCampaign)),
                    'class_name' => $className,
                    ...$publishResult,
                ],
                'reason' => 'Terbit peserta Jogathon dengan alias dan slug awam yang tidak menggunakan nama penuh.',
                'actor_user_id' => $request->user()?->id,
            ]);

            return $publishResult;
        }, 3);

        return redirect()->route('system.jogathon.campaigns.index', ['campaign' => $jogathonCampaign->id])
            ->with('status', sprintf(
                'Terbit peserta selesai: %d diterbitkan, %d slug diganti, %d nama paparan dialiaskan.',
                $result['published'],
                $result['slug_rotated'],
                $result['aliases_reset'],
            ));
    }

    private function validateCampaign(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(JogathonCampaign::statusOptions()))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'default_target_amount_rm' => ['required', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/', 'not_regex:/^0+(?:\.0{1,2})?$/'],
            'show_class_publicly' => ['nullable', 'boolean'],
            'allow_public_indexing' => ['nullable', 'boolean'],
            'allow_unspecified_cause' => ['nullable', 'boolean'],
        ]);

        $amountSen = JogathonAmount::senFromRinggit((string) $validated['default_target_amount_rm']);

        return [
            'name' => trim((string) $validated['name']),
            'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
            'status' => $validated['status'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'default_target_amount_sen' => $amountSen,
            'default_target_distance_cm' => JogathonAmount::distanceCmFromSen($amountSen),
            'show_class_publicly' => (bool) ($validated['show_class_publicly'] ?? false),
            'allow_public_indexing' => (bool) ($validated['allow_public_indexing'] ?? false),
            'allow_unspecified_cause' => (bool) ($validated['allow_unspecified_cause'] ?? false),
            'year_to_tahap' => config('jogathon.year_to_tahap'),
        ];
    }

    private function participantVisitsTableExists(): bool
    {
        try {
            return Schema::hasTable('jogathon_participant_visits');
        } catch (\Throwable) {
            return false;
        }
    }
}
