<x-layouts::app :title="__('Parent Management')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-600">System Admin</p>
                    <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-zinc-900">Parent Management</h1>
                    <p class="mt-1 text-sm text-zinc-600">Review parent accounts, dual-role users, linked children, and parent portal readiness in one place.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Parent Accounts</p>
                        <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($rows->where('has_account', true)->count()) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Dual Role</p>
                        <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($rows->filter(fn ($row) => in_array('parent', $row['role_names'] ?? [], true) && count($row['role_names'] ?? []) > 1)->count()) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Blocked</p>
                        <p class="mt-1 text-2xl font-bold text-rose-700">{{ number_format($rows->where('access_status', 'blocked')->count()) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">No Account</p>
                        <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($rows->where('has_account', false)->count()) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white/90 shadow-sm">
            <div class="border-b border-zinc-200 bg-zinc-50 px-4 py-4">
                <form method="GET" action="{{ route('teacher.parent-management.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600 xl:col-span-2">
                        Search
                        <input
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Parent, phone, email, child"
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
                        Payment
                        <select name="payment_status" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" @selected($paymentStatus === 'all')>All</option>
                            <option value="paid" @selected($paymentStatus === 'paid')>Paid</option>
                            <option value="partial" @selected($paymentStatus === 'partial')>Partial</option>
                            <option value="unpaid" @selected($paymentStatus === 'unpaid')>Unpaid</option>
                        </select>
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Account
                        <select name="account_filter" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" @selected($accountFilter === 'all')>All</option>
                            <option value="has_account" @selected($accountFilter === 'has_account')>Has account</option>
                            <option value="no_account" @selected($accountFilter === 'no_account')>No account</option>
                        </select>
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Access
                        <select name="access_filter" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" @selected($accessFilter === 'all')>All</option>
                            <option value="active" @selected($accessFilter === 'active')>Active</option>
                            <option value="blocked" @selected($accessFilter === 'blocked')>Blocked</option>
                            <option value="no_account" @selected($accessFilter === 'no_account')>No account</option>
                        </select>
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Roles
                        <select name="role_filter" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" @selected($roleFilter === 'all')>All</option>
                            <option value="parent_only" @selected($roleFilter === 'parent_only')>Parent only</option>
                            <option value="dual_role" @selected($roleFilter === 'dual_role')>Teacher + parent</option>
                        </select>
                    </label>

                    <div class="md:col-span-2 xl:col-span-6 flex items-center gap-2">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                            Apply Filters
                        </button>
                        <a href="{{ route('teacher.parent-management.index') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100">
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
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Linked Students</th>
                            <th class="px-4 py-3">Classes</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3">Access</th>
                            <th class="px-4 py-3">Roles</th>
                            <th class="px-4 py-3">Last Activity</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm text-zinc-800">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-zinc-900">{{ $row['name'] ?: '-' }}</p>
                                    @if (($row['family_codes'] ?? []) !== [])
                                        <p class="mt-1 text-xs text-zinc-500">{{ implode(', ', $row['family_codes']) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <p>{{ $row['phone'] ?: '-' }}</p>
                                    <p class="text-xs text-zinc-500">{{ $row['email'] ?: 'No email' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="max-w-xs space-y-1">
                                        @forelse (array_slice($row['linked_students'] ?? [], 0, 3) as $studentName)
                                            <p class="rounded-lg bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700">{{ $studentName }}</p>
                                        @empty
                                            <p class="text-xs text-zinc-500">No linked students</p>
                                        @endforelse
                                        @if (count($row['linked_students'] ?? []) > 3)
                                            <p class="text-xs text-zinc-500">+{{ count($row['linked_students']) - 3 }} more</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ ($row['class_names'] ?? []) !== [] ? implode(', ', $row['class_names']) : '-' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $paymentBadgeClass = match ($row['payment_status']) {
                                            'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'partial' => 'border-amber-200 bg-amber-50 text-amber-700',
                                            default => 'border-rose-200 bg-rose-50 text-rose-700',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $paymentBadgeClass }}">
                                        {{ ucfirst($row['payment_status']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $accessBadgeClass = match ($row['access_status']) {
                                            'active' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'blocked' => 'border-rose-200 bg-rose-50 text-rose-700',
                                            default => 'border-zinc-200 bg-zinc-100 text-zinc-700',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $accessBadgeClass }}">
                                        {{ str_replace('_', ' ', ucfirst($row['access_status'])) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($row['role_names'] as $roleName)
                                            <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
                                                {{ str_replace('_', ' ', strtoupper($roleName === 'system_admin' ? 'admin' : $roleName)) }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-zinc-500">No account</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <p>{{ $row['last_activity_at'] ? $row['last_activity_at']->format('d M Y H:i') : '-' }}</p>
                                    <p class="text-xs text-zinc-500">Login: {{ $row['last_login_at'] ? $row['last_login_at']->format('d M Y H:i') : '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($row['detail_url'])
                                        <a href="{{ $row['detail_url'] }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100">
                                            {{ $row['has_account'] ? 'View profile' : 'Open family' }}
                                        </a>
                                    @else
                                        <span class="text-xs text-zinc-400">No action</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-zinc-500">No parent records match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
