<?php

namespace App\Http\Controllers;

use App\Models\ApiAccessLog;
use App\Models\TeacherApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherApiAccessController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('teacher.api-access.docs');
    }

    public function docs(): View
    {
        return view('teacher.api-access.docs');
    }

    public function keys(Request $request): View
    {
        $teacher = $request->user();

        $apiKey = TeacherApiKey::query()
            ->where('teacher_id', $teacher->id)
            ->latest('id')
            ->first();

        return view('teacher.api-access.keys', [
            'apiKey' => $apiKey,
            'plainKey' => session('plain_api_key'),
        ]);
    }

    public function stats(Request $request): View
    {
        $teacher = $request->user();

        $apiKey = TeacherApiKey::query()
            ->where('teacher_id', $teacher->id)
            ->latest('id')
            ->first();

        $todayStart = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $baseLogsQuery = ApiAccessLog::query()->where('teacher_id', $teacher->id);
        $logs = ApiAccessLog::query()
            ->where('teacher_id', $teacher->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('teacher.api-access.stats', [
            'apiKey' => $apiKey,
            'summary' => [
                'today_calls' => (clone $baseLogsQuery)->where('created_at', '>=', $todayStart)->count(),
                'month_calls' => (clone $baseLogsQuery)->where('created_at', '>=', $monthStart)->count(),
                'last_used' => $apiKey?->last_used_at,
                'success_count' => (clone $baseLogsQuery)->whereBetween('response_status', [200, 299])->count(),
                'failed_count' => (clone $baseLogsQuery)->where('response_status', '>=', 400)->count(),
            ],
            'logs' => $logs,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        $activeKeyExists = TeacherApiKey::query()
            ->where('teacher_id', $teacher->id)
            ->active()
            ->exists();

        if ($activeKeyExists) {
            return back()->with('status', 'An active API key already exists. Use Regenerate to replace it.');
        }

        $plainKey = $this->createKeyForTeacher((int) $teacher->id);

        return back()
            ->with('status', 'API key generated. Copy it now because the full key is shown only once.')
            ->with('plain_api_key', $plainKey);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $teacher = $request->user();

        TeacherApiKey::query()
            ->where('teacher_id', $teacher->id)
            ->active()
            ->get()
            ->each(fn (TeacherApiKey $apiKey) => $apiKey->revoke($teacher));

        $plainKey = $this->createKeyForTeacher((int) $teacher->id);

        return back()
            ->with('status', 'API key regenerated. The previous key has been revoked.')
            ->with('plain_api_key', $plainKey);
    }

    public function revoke(Request $request): RedirectResponse
    {
        $teacher = $request->user();

        TeacherApiKey::query()
            ->where('teacher_id', $teacher->id)
            ->active()
            ->get()
            ->each(fn (TeacherApiKey $apiKey) => $apiKey->revoke($teacher));

        return back()->with('status', 'API key revoked.');
    }

    private function createKeyForTeacher(int $teacherId): string
    {
        $plainKey = TeacherApiKey::generatePlainKey();

        TeacherApiKey::query()->create([
            'teacher_id' => $teacherId,
            'key_hash' => TeacherApiKey::hashPlainKey($plainKey),
            'key_prefix' => 'pibg_live',
            'last_four' => substr($plainKey, -4),
            'status' => TeacherApiKey::STATUS_ACTIVE,
        ]);

        return $plainKey;
    }
}
