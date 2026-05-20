<?php

namespace App\Http\Controllers;

use App\Services\ParentAccessLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalSpaceController extends Controller
{
    public function __construct(
        private readonly ParentAccessLogService $parentAccessLogService
    ) {
    }

    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'space' => ['required', 'in:parent,teacher'],
        ]);

        $user = $request->user();
        abort_unless($user, 403);

        $space = (string) $validated['space'];

        if ($space === 'parent') {
            abort_unless($user->isParent(), 403);
        } else {
            abort_unless($user->isStaff(), 403);
        }

        $request->session()->put('active_portal_space', $space);

        if ($user->isParent()) {
            $this->parentAccessLogService->log($request, $space === 'parent' ? 'parent_space_opened' : 'teacher_space_opened', [
                'user' => $user,
                'space_key' => $space,
                'page_visited' => $request->route()?->getName() ?? 'portal-space.switch',
            ]);
        }

        if ($space === 'parent') {
            return redirect()->route('parent.dashboard');
        }

        return redirect()->route(
            $user->hasAnyRole(['teacher', 'super_teacher']) ? 'teacher.dashboard' : 'dashboard'
        );
    }
}
