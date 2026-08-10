<?php

namespace App\Http\Controllers;

use App\Models\JogathonCampaign;
use App\Models\JogathonParticipant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JogathonTeacherCardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $campaign = JogathonCampaign::query()
            ->whereNull('archived_at')
            ->whereIn('status', [
                JogathonCampaign::STATUS_SCHEDULED,
                JogathonCampaign::STATUS_ACTIVE,
                JogathonCampaign::STATUS_COMPLETED,
            ])
            ->latest('id')
            ->first();

        $participants = $campaign
            ? JogathonParticipant::query()
                ->with('student:id,full_name,class_name,status')
                ->where('campaign_id', $campaign->id)
                ->where('is_eligible', true)
                ->where('participation_opt_out', false)
                ->whereNull('withdrawn_at')
                ->when($this->isClassTeacherOnly($user), fn ($query) => $query->where('class_name_snapshot', $user->class_name))
                ->orderBy('class_name_snapshot')
                ->orderBy('public_display_name')
                ->paginate(40)
                ->withQueryString()
            : null;

        return view('teacher.jogathon.cards', [
            'campaign' => $campaign,
            'participants' => $participants,
            'isClassTeacherOnly' => $this->isClassTeacherOnly($user),
        ]);
    }

    private function isClassTeacherOnly(User $user): bool
    {
        return $user->hasRole('teacher')
            && ! $user->hasAnyRole(['super_teacher', 'system_admin', 'admin', 'super_admin']);
    }
}
