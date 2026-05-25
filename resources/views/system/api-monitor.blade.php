<x-layouts::app :title="__('API Monitor')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">System Admin</p>
                    <h1 class="text-2xl font-bold text-zinc-900">API Monitor</h1>
                    <p class="mt-1 text-sm text-zinc-600">Monitor teacher payment-status API usage, failures, and key health.</p>
                </div>
                <a href="{{ route('admin.api-monitor.export', request()->query()) }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100">Export CSV</a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Calls Today</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($summary['today_calls']) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Calls This Month</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($summary['month_calls']) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Active API Keys</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($summary['active_keys']) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Failed Attempts</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ number_format($summary['failed_attempts']) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Most Active Teacher</p>
                    <p class="mt-1 text-lg font-bold text-zinc-900">{{ $summary['most_active_teacher'] }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 bg-zinc-50 px-4 py-4">
                <form method="GET" action="{{ route('admin.api-monitor.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Teacher
                        <select name="teacher_id" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="">All teachers</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === (string) $teacher->id)>{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Date From
                        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Date To
                        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Status
                        <select name="status" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="">All</option>
                            <option value="success" @selected($filters['status'] === 'success')>Success</option>
                            <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Endpoint
                        <input type="search" name="endpoint" value="{{ $filters['endpoint'] }}" placeholder="/api/v1/..." class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                    </label>
                    <div class="flex items-end gap-2">
                        <button class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">Filter</button>
                        <a href="{{ route('admin.api-monitor.index') }}" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100">Reset</a>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Date/Time</th>
                            <th class="px-4 py-3">Teacher</th>
                            <th class="px-4 py-3">Endpoint</th>
                            <th class="px-4 py-3">Search Query</th>
                            <th class="px-4 py-3">Result</th>
                            <th class="px-4 py-3">IP</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($logs as $log)
                            @php
                                $statusClass = $log->response_status >= 400
                                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-700">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->teacher?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->endpoint }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->query_text ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ number_format($log->result_count) }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->request_ip ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $log->response_status }}</span>
                                </td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->execution_time_ms }} ms</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-zinc-500">No API access logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 px-4 py-4">{{ $logs->links() }}</div>
        </section>

    </div>
</x-layouts::app>
