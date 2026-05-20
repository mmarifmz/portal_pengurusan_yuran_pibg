<?php

namespace App\Http\Controllers;

use App\Services\ParentAccessLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __construct(
        private readonly ParentAccessLogService $parentAccessLogService
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user?->isParent()) {
            $this->parentAccessLogService->log($request, 'logout', [
                'user' => $user,
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
