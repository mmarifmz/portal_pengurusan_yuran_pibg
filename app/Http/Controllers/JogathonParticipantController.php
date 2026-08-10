<?php

namespace App\Http\Controllers;

use App\Models\JogathonAudit;
use App\Models\JogathonParticipant;
use App\Support\JogathonAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JogathonParticipantController extends Controller
{
    public function update(Request $request, JogathonParticipant $jogathonParticipant): RedirectResponse
    {
        $validated = $request->validate([
            'public_display_name' => ['required', 'string', 'max:120'],
            'target_amount_rm' => ['required', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/', 'not_regex:/^0+(?:\.0{1,2})?$/'],
            'is_published' => ['nullable', 'boolean'],
            'participation_opt_out' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $before = $jogathonParticipant->only(['public_display_name', 'target_amount_sen', 'target_distance_cm', 'is_published', 'participation_opt_out']);
        $amountSen = JogathonAmount::senFromRinggit((string) $validated['target_amount_rm']);
        $optOut = (bool) ($validated['participation_opt_out'] ?? false);

        $jogathonParticipant->update([
            'public_display_name' => trim((string) $validated['public_display_name']),
            'target_amount_sen' => $amountSen,
            'target_distance_cm' => JogathonAmount::distanceCmFromSen($amountSen),
            'is_published' => ! $optOut && $jogathonParticipant->is_eligible && (bool) ($validated['is_published'] ?? false),
            'participation_opt_out' => $optOut,
        ]);

        JogathonAudit::query()->create([
            'campaign_id' => $jogathonParticipant->campaign_id,
            'auditable_type' => JogathonParticipant::class,
            'auditable_id' => $jogathonParticipant->id,
            'action' => 'participant.publication_updated',
            'before_values' => $before,
            'after_values' => $jogathonParticipant->fresh()->only(array_keys($before)),
            'reason' => $validated['reason'],
            'actor_user_id' => $request->user()?->id,
        ]);

        return back()->with('status', 'Tetapan peserta Jogathon dikemas kini tanpa mengubah slug awam.');
    }
}
