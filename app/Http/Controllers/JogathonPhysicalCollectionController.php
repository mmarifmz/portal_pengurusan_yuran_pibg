<?php

namespace App\Http\Controllers;

use App\Models\JogathonAudit;
use App\Models\JogathonContribution;
use App\Models\JogathonParticipant;
use App\Models\User;
use App\Support\JogathonAmount;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JogathonPhysicalCollectionController extends Controller
{
    public function updateCardNumber(Request $request, JogathonParticipant $jogathonParticipant): RedirectResponse
    {
        abort_unless(JogathonParticipant::hasPhysicalCardNumberColumn(), 503);

        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless($this->canEnterForParticipant($user, $jogathonParticipant), 403);
        abort_unless($jogathonParticipant->is_eligible && ! $jogathonParticipant->participation_opt_out && $jogathonParticipant->withdrawn_at === null, 422);

        $request->merge([
            'physical_card_number' => JogathonParticipant::normalizePhysicalCardNumber($request->input('physical_card_number')),
        ]);

        $validated = $request->validate([
            'physical_card_number' => [
                'required',
                'string',
                'max:32',
                'regex:/^ssp-[0-9]{4,8}$/',
                Rule::unique('jogathon_participants', 'physical_card_number')->ignore($jogathonParticipant->id),
            ],
        ]);

        $before = $jogathonParticipant->only(['physical_card_number']);
        $jogathonParticipant->forceFill([
            'physical_card_number' => $validated['physical_card_number'],
        ])->save();

        JogathonAudit::query()->create([
            'campaign_id' => $jogathonParticipant->campaign_id,
            'auditable_type' => JogathonParticipant::class,
            'auditable_id' => $jogathonParticipant->id,
            'action' => 'participant.physical_card_number_registered',
            'before_values' => $before,
            'after_values' => $jogathonParticipant->fresh()->only(['physical_card_number']),
            'reason' => 'Daftar nombor kad fizikal sebagai slug peserta Jogathon.',
            'actor_user_id' => $user->id,
        ]);

        return back()->with('status', sprintf(
            'Nombor kad %s didaftarkan untuk %s.',
            $validated['physical_card_number'],
            $jogathonParticipant->public_display_name,
        ));
    }

    public function store(Request $request, JogathonParticipant $jogathonParticipant): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless($this->canEnterForParticipant($user, $jogathonParticipant), 403);
        abort_unless($jogathonParticipant->is_eligible && ! $jogathonParticipant->participation_opt_out && $jogathonParticipant->withdrawn_at === null, 422);

        $campaign = $jogathonParticipant->campaign()->firstOrFail();
        $causeIds = $campaign->causes()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'amount_rm' => ['required', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/', 'not_regex:/^0+(?:\.0{1,2})?$/'],
            'cause_id' => ['required', 'integer', Rule::in($causeIds)],
            'donor_display_name' => ['nullable', 'string', 'max:120'],
            'collection_reference' => ['nullable', 'string', 'max:120'],
            'received_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:280'],
        ]);

        $amountSen = JogathonAmount::senFromRinggit((string) $validated['amount_rm']);
        $receivedAt = filled($validated['received_on'] ?? null)
            ? CarbonImmutable::parse((string) $validated['received_on'])->startOfDay()
            : now();

        $contribution = DB::transaction(function () use ($amountSen, $campaign, $jogathonParticipant, $request, $receivedAt, $user, $validated): JogathonContribution {
            $contribution = JogathonContribution::query()->create([
                'campaign_id' => $campaign->id,
                'participant_id' => $jogathonParticipant->id,
                'cause_id' => (int) $validated['cause_id'],
                'source' => JogathonContribution::SOURCE_PHYSICAL_CARD,
                'amount_sen' => $amountSen,
                'status' => JogathonContribution::STATUS_FINALISED,
                'donor_display_name' => filled($validated['donor_display_name'] ?? null)
                    ? trim((string) $validated['donor_display_name'])
                    : 'Kutipan Kad Fizikal',
                'is_anonymous_public' => true,
                'received_at' => $receivedAt,
                'finalised_at' => now(),
                'entered_by_user_id' => $user->id,
                'metadata' => [
                    'entry_channel' => 'physical_card_admin',
                    'collection_reference' => filled($validated['collection_reference'] ?? null) ? trim((string) $validated['collection_reference']) : null,
                    'note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
                    'actor_role_names' => $user->roleNames(),
                    'request_ip' => $request->ip(),
                ],
            ]);

            JogathonAudit::query()->create([
                'campaign_id' => $campaign->id,
                'auditable_type' => JogathonContribution::class,
                'auditable_id' => $contribution->id,
                'action' => 'physical_contribution.finalised',
                'after_values' => [
                    'participant_id' => $jogathonParticipant->id,
                    'cause_id' => $contribution->cause_id,
                    'amount_sen' => $contribution->amount_sen,
                    'distance_cm' => $contribution->distance_cm,
                    'source' => $contribution->source,
                    'status' => $contribution->status,
                    'received_at' => optional($contribution->received_at)->toISOString(),
                ],
                'reason' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : 'Kutipan kad fizikal Jogathon',
                'actor_user_id' => $user->id,
            ]);

            return $contribution;
        });

        return back()->with('status', sprintf(
            'Kutipan fizikal RM%s direkodkan untuk %s.',
            number_format($contribution->amount_sen / 100, 2),
            $jogathonParticipant->public_display_name,
        ));
    }

    private function canEnterForParticipant(User $user, JogathonParticipant $participant): bool
    {
        if ($user->hasAnyRole(['system_admin', 'admin', 'super_admin', 'super_teacher'])) {
            return true;
        }

        if (! $user->hasRole('teacher')) {
            return false;
        }

        $teacherClass = mb_strtoupper(trim((string) $user->class_name));
        $participantClass = mb_strtoupper(trim((string) $participant->class_name_snapshot));

        return $teacherClass !== '' && $teacherClass === $participantClass;
    }
}
