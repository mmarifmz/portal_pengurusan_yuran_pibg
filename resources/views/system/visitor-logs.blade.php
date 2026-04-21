<x-layouts::app :title="__('Visitor Logs')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">System Admin</p>
            <h1 class="text-2xl font-bold text-zinc-900">Visitor Logs</h1>
            <p class="text-sm text-zinc-500">Track public and authenticated web visits (IP, URL, browser, and timestamp).</p>
        </div>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('system.visitor-logs.index') }}" class="grid gap-3 sm:grid-cols-[1fr_auto_auto_auto_auto]">
                <label class="text-sm font-medium text-zinc-700">
                    Search
                    <input
                        name="q"
                        type="text"
                        value="{{ $keyword }}"
                        placeholder="IP, URL, browser, device..."
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <label class="text-sm font-medium text-zinc-700">
                    Method
                    <select
                        name="method"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    >
                        <option value="">All</option>
                        @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $httpMethod)
                            <option value="{{ $httpMethod }}" @selected($method === $httpMethod)>{{ $httpMethod }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm font-medium text-zinc-700">
                    Date From
                    <input
                        name="date_from"
                        type="date"
                        value="{{ $dateFrom }}"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <label class="text-sm font-medium text-zinc-700">
                    Date To
                    <input
                        name="date_to"
                        type="date"
                        value="{{ $dateTo }}"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <div class="self-end">
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                            Filter
                        </button>
                        <a
                            href="{{ route('system.visitor-logs.export', array_filter(['q' => $keyword, 'method' => $method, 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}"
                            class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50"
                        >
                            Export CSV
                        </a>
                    </div>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">When</th>
                            <th class="px-4 py-3">IP</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">URL</th>
                            <th class="px-4 py-3">Browser / Platform</th>
                            <th class="px-4 py-3">Visitor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($visits as $visit)
                            <tr>
                                <td class="px-4 py-3 text-zinc-700 whitespace-nowrap">{{ $visit->created_at?->format('d M Y H:i:s') }}</td>
                                <td class="px-4 py-3 text-zinc-700 whitespace-nowrap">{{ $visit->ip ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">
                                        {{ $visit->method ?: '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-zinc-700 max-w-[28rem] truncate" title="{{ $visit->url }}">{{ $visit->url ?: '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700">
                                    <div>{{ $visit->browser ?: '-' }}</div>
                                    <div class="text-xs text-zinc-500">{{ $visit->platform ?: '-' }} · {{ $visit->device ?: '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-zinc-700 whitespace-nowrap">
                                    @if ($visit->visitor_type && $visit->visitor_id)
                                        {{ class_basename($visit->visitor_type) }} #{{ $visit->visitor_id }}
                                    @else
                                        Guest
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-zinc-500">No visitor logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $visits->links() }}
            </div>
        </section>
    </div>
</x-layouts::app>
