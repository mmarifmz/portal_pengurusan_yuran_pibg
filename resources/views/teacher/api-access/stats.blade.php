<x-layouts::app :title="__('API Usage Stats')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">API Access</p>
            <h1 class="mt-1 text-2xl font-bold text-zinc-900">API Usage Stats</h1>
            <p class="mt-2 max-w-2xl text-sm text-zinc-600">Review your recent API calls, success rate, failures, and last-used timestamp.</p>

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
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Last Used</p>
                    <p class="mt-1 text-lg font-bold text-zinc-900">{{ $summary['last_used']?->format('d M Y H:i') ?? '-' }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Success</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($summary['success_count']) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Failed</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ number_format($summary['failed_count']) }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-zinc-900">Recent API Calls</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">When</th>
                            <th class="px-4 py-3">Endpoint</th>
                            <th class="px-4 py-3">Query</th>
                            <th class="px-4 py-3">Results</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Execution</th>
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
                                <td class="px-4 py-3 text-zinc-700">{{ $log->endpoint }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->query_text ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ number_format($log->result_count) }}</td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $log->response_status }}</span></td>
                                <td class="px-4 py-3 text-zinc-700">{{ $log->execution_time_ms }} ms</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-zinc-500">No API calls yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $logs->links() }}</div>
        </section>
    </div>
</x-layouts::app>
