<?php

namespace App\Http\Controllers;

use App\Models\JogathonAudit;
use App\Models\JogathonCampaign;
use App\Models\JogathonCause;
use App\Support\JogathonAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JogathonCauseController extends Controller
{
    public function store(Request $request, JogathonCampaign $jogathonCampaign): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('jogathon_causes')->where('campaign_id', $jogathonCampaign->id)],
            'target_amount_rm' => ['required', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/', 'not_regex:/^0+(?:\.0{1,2})?$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        $cause = $jogathonCampaign->causes()->create([
            'name' => trim((string) $validated['name']),
            'target_amount_sen' => JogathonAmount::senFromRinggit((string) $validated['target_amount_rm']),
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => true,
        ]);

        $this->audit($cause, 'cause.created', null, $cause->toArray(), $request);

        return back()->with('status', 'Tujuan kempen Jogathon ditambah.');
    }

    public function update(Request $request, JogathonCause $jogathonCause): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('jogathon_causes')->where('campaign_id', $jogathonCause->campaign_id)->ignore($jogathonCause)],
            'target_amount_rm' => ['required', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/', 'not_regex:/^0+(?:\.0{1,2})?$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = $jogathonCause->toArray();
        $jogathonCause->update([
            'name' => trim((string) $validated['name']),
            'target_amount_sen' => JogathonAmount::senFromRinggit((string) $validated['target_amount_rm']),
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);
        $this->audit($jogathonCause, 'cause.updated', $before, $jogathonCause->fresh()->toArray(), $request);

        return back()->with('status', 'Tujuan kempen Jogathon dikemas kini.');
    }

    public function archive(Request $request, JogathonCause $jogathonCause): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $before = $jogathonCause->toArray();
        $jogathonCause->update(['is_active' => false, 'archived_at' => now()]);
        $this->audit($jogathonCause, 'cause.archived', $before, $jogathonCause->fresh()->toArray(), $request, $validated['reason']);

        return back()->with('status', 'Tujuan kempen Jogathon diarkibkan.');
    }

    private function audit(JogathonCause $cause, string $action, ?array $before, array $after, Request $request, ?string $reason = null): void
    {
        JogathonAudit::query()->create([
            'campaign_id' => $cause->campaign_id,
            'auditable_type' => JogathonCause::class,
            'auditable_id' => $cause->id,
            'action' => $action,
            'before_values' => $before,
            'after_values' => $after,
            'reason' => $reason,
            'actor_user_id' => $request->user()?->id,
        ]);
    }
}
