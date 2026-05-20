<x-layouts::app :title="__('Parent Access Log')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900">Parent Access Log</h1>
                    <p class="mt-1 text-sm text-zinc-600">Track parent logins, payment interactions, blocked access, and dual-role activity across the portal.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('teacher.family-login-monitor.export', request()->query()) }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100">
                        Export CSV
                    </a>
                    <p class="text-xs font-medium text-zinc-500">Generated: {{ $generatedAt->format('d M Y H:i') }}</p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Visits Today</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($summary['total_parent_visits_today']) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Unique Parents Today</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($summary['unique_parents_active_today']) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Blocked Attempts</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700">{{ number_format($summary['blocked_access_attempts']) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Most Active Class</p>
                    <p class="mt-1 text-lg font-bold text-zinc-900">{{ $summary['most_active_class'] }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Inactive 30 Days</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($summary['parents_not_active_30_days']) }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white/90 shadow-sm">
            <div class="border-b border-zinc-200 bg-zinc-50 px-4 py-4">
                <form method="GET" action="{{ route('teacher.family-login-monitor') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600 xl:col-span-2">
                        Search
                        <input
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Parent name / phone / child"
                            class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Class
                        <select name="class_name" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="">All classes</option>
                            @foreach ($classOptions as $className)
                                <option value="{{ $className }}" @selected($selectedClass === $className)>{{ $className }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Action
                        <select name="action_type" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" @selected($selectedAction === 'all')>All</option>
                            @foreach ($actionOptions as $actionOption)
                                <option value="{{ $actionOption }}" @selected($selectedAction === $actionOption)>{{ str_replace('_', ' ', $actionOption) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Access
                        <select name="access_filter" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" @selected($selectedAccess === 'all')>All</option>
                            <option value="successful" @selected($selectedAccess === 'successful')>Successful</option>
                            <option value="failed" @selected($selectedAccess === 'failed')>Failed</option>
                            <option value="blocked" @selected($selectedAccess === 'blocked')>Blocked</option>
                        </select>
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Role Mode
                        <select name="role_mode" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" @selected($selectedRoleMode === 'all')>All parents</option>
                            <option value="teacher_parent" @selected($selectedRoleMode === 'teacher_parent')>Teacher + parent</option>
                        </select>
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Date From
                        <input type="date" name="date_from" value="{{ $dateFromInput }}" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Date To
                        <input type="date" name="date_to" value="{{ $dateToInput }}" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" />
                    </label>

                    <div class="md:col-span-2 xl:col-span-7 flex items-center gap-2">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                            Apply Filters
                        </button>
                        <a href="{{ route('teacher.family-login-monitor') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">
                            <th class="px-4 py-3">Parent</th>
                            <th class="px-4 py-3">Linked Students</th>
                            <th class="px-4 py-3">Class</th>
                            <th class="px-4 py-3">Page</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Device</th>
                            <th class="px-4 py-3">Login Method</th>
                            <th class="px-4 py-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm text-zinc-800">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-zinc-900">{{ $row['parent_name'] }}</p>
                                    <p class="text-xs text-zinc-500">{{ $row['phone'] ?: '-' }} @if($row['email']) · {{ $row['email'] }} @endif</p>
                                    @if ($row['roles_display'] !== '')
                                        <p class="mt-1 text-[11px] font-semibold text-zinc-500">{{ $row['roles_display'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $row['students_display'] ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $row['class_display'] ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <p>{{ $row['page_visited'] }}</p>
                                    <p class="text-xs text-zinc-500">{{ $row['ip_address'] }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border border-zinc-200 bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">
                                        {{ str_replace('_', ' ', $row['action_type']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusClass = match ($row['access_status']) {
                                            'blocked' => 'border-rose-200 bg-rose-50 text-rose-700',
                                            'failed' => 'border-amber-200 bg-amber-50 text-amber-700',
                                            default => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucfirst($row['access_status']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $row['device_browser'] }}</td>
                                <td class="px-4 py-3">{{ $row['login_method'] }}</td>
                                <td class="px-4 py-3">{{ $row['occurred_at'] ? $row['occurred_at']->format('d M Y H:i:s') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-zinc-500">No parent access records found for the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
