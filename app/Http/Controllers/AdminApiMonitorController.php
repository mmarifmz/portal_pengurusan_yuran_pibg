<?php

namespace App\Http\Controllers;

use App\Models\ApiAccessLog;
use App\Models\TeacherApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminApiMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $logsQuery = $this->filteredLogsQuery($filters);

        $todayStart = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $mostActiveTeacherId = ApiAccessLog::query()
            ->whereNotNull('teacher_id')
            ->where('created_at', '>=', $monthStart)
            ->select('teacher_id', DB::raw('COUNT(*) as total_calls'))
            ->groupBy('teacher_id')
            ->orderByDesc('total_calls')
            ->value('teacher_id');

        $summary = [
            'today_calls' => ApiAccessLog::query()->where('created_at', '>=', $todayStart)->count(),
            'month_calls' => ApiAccessLog::query()->where('created_at', '>=', $monthStart)->count(),
            'active_keys' => TeacherApiKey::query()->active()->count(),
            'failed_attempts' => ApiAccessLog::query()->where('response_status', '>=', 400)->count(),
            'most_active_teacher' => $mostActiveTeacherId
                ? (string) User::query()->find($mostActiveTeacherId)?->name
                : '-',
        ];

        return view('system.api-monitor', [
            'summary' => $summary,
            'logs' => $logsQuery->latest()->paginate(30)->withQueryString(),
            'teachers' => User::query()->withAnyRole(['teacher', 'super_teacher', 'system_admin'])->orderBy('name')->get(['id', 'name', 'email']),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $logs = $this->filteredLogsQuery($filters)->with('teacher')->latest()->get();

        return response()->streamDownload(function () use ($logs): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Date Time',
                'Teacher',
                'Endpoint',
                'Method',
                'Search Query',
                'Result Count',
                'IP Address',
                'Status',
                'Execution Time MS',
                'Error',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->teacher?->name ?? '-',
                    $log->endpoint,
                    $log->method,
                    $log->query_text,
                    $log->result_count,
                    $log->request_ip,
                    $log->response_status,
                    $log->execution_time_ms,
                    $log->error_message,
                ]);
            }

            fclose($handle);
        }, 'api-access-logs.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function keyRegistry(): View
    {
        return view('system.api-key-registry', [
            'apiKeys' => TeacherApiKey::query()
                ->with('teacher')
                ->latest()
                ->paginate(30),
        ]);
    }

    public function revoke(Request $request, TeacherApiKey $teacherApiKey): RedirectResponse
    {
        $teacherApiKey->revoke($request->user());

        return back()->with('status', 'Teacher API key revoked.');
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return [
            'teacher_id' => trim((string) $request->query('teacher_id', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'status' => trim((string) $request->query('status', '')),
            'endpoint' => trim((string) $request->query('endpoint', '')),
        ];
    }

    /**
     * @param array<string, string> $filters
     */
    private function filteredLogsQuery(array $filters): Builder
    {
        return ApiAccessLog::query()
            ->with('teacher')
            ->when($filters['teacher_id'] !== '', fn (Builder $query) => $query->where('teacher_id', (int) $filters['teacher_id']))
            ->when($filters['endpoint'] !== '', fn (Builder $query) => $query->where('endpoint', 'like', '%'.$filters['endpoint'].'%'))
            ->when($filters['status'] === 'success', fn (Builder $query) => $query->whereBetween('response_status', [200, 299]))
            ->when($filters['status'] === 'failed', fn (Builder $query) => $query->where('response_status', '>=', 400))
            ->when($filters['date_from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay()))
            ->when($filters['date_to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay()));
    }
}
