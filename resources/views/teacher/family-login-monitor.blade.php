<x-layouts::app :title="__('Family Login Monitor')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900">Registered Family Login Monitor</h1>
                    <p class="mt-1 text-sm text-zinc-600">Monitor family phone registrations, TAC history, and parent login activity.</p>
                </div>
                <p class="text-xs font-medium text-zinc-500">Generated: {{ $generatedAt->format('d M Y H:i') }}</p>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Registered Families</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($totalFamilies) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Total Logins</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($totalLoginCount) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">TAC Sent</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($totalTacSentCount) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">TAC Stuck Families</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($totalTacStuckFamilies) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Paid Families</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($rows->where('is_paid', true)->count()) }}</p>
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
                            placeholder="Family code / phone / class"
                            class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Paid Status
                        <select name="paid_status" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" @selected($paidStatus === 'all')>All</option>
                            <option value="paid" @selected($paidStatus === 'paid')>Paid only</option>
                            <option value="unpaid" @selected($paidStatus === 'unpaid')>Unpaid only</option>
                        </select>
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
                        TAC Status
                        <select name="tac_status" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="all" @selected($tacStatus === 'all')>All</option>
                            <option value="stuck" @selected($tacStatus === 'stuck')>Stuck only</option>
                            <option value="completed" @selected($tacStatus === 'completed')>Completed</option>
                            <option value="pending" @selected($tacStatus === 'pending')>Pending TAC</option>
                            <option value="expired" @selected($tacStatus === 'expired')>Expired TAC</option>
                            <option value="no_request" @selected($tacStatus === 'no_request')>No TAC request</option>
                        </select>
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Login From
                        <input
                            type="date"
                            name="date_from"
                            value="{{ $dateFromInput }}"
                            class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Login To
                        <input
                            type="date"
                            name="date_to"
                            value="{{ $dateToInput }}"
                            class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-normal text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
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
                            <th class="px-4 py-3">Family Code</th>
                            <th class="px-4 py-3">Phone Number</th>
                            <th class="px-4 py-3">Class</th>
                            <th class="px-4 py-3 text-right">Login Count</th>
                            <th class="px-4 py-3">Latest Login</th>
                            <th class="px-4 py-3 text-right">TAC Sent</th>
                            <th class="px-4 py-3 text-right">TAC Used</th>
                            <th class="px-4 py-3 text-right">Pending</th>
                            <th class="px-4 py-3 text-right">Expired</th>
                            <th class="px-4 py-3">Last TAC Sent</th>
                            <th class="px-4 py-3">TAC Status</th>
                            <th class="px-4 py-3">Yuran Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm text-zinc-800">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-zinc-900">
                                    <a
                                        href="{{ route('teacher.records.family', ['familyCode' => $row['family_code'], 'payment_status' => 'all']) }}"
                                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-emerald-700 underline decoration-emerald-300 underline-offset-2 transition hover:text-emerald-800 hover:decoration-emerald-500"
                                    >
                                        {{ $row['family_code'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">{{ $row['phones_display'] !== '' ? $row['phones_display'] : '-' }}</td>
                                <td class="px-4 py-3">{{ $row['class_display'] !== '' ? $row['class_display'] : '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['login_count']) }}</td>
                                <td class="px-4 py-3">{{ $row['latest_login_at'] ? $row['latest_login_at']->format('d M Y H:i:s') : '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['tac_sent_count']) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['tac_verified_count']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tac_pending_count']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tac_expired_count']) }}</td>
                                <td class="px-4 py-3">{{ $row['latest_tac_sent_at'] ? $row['latest_tac_sent_at']->format('d M Y H:i:s') : '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['is_tac_stuck'] && $row['tac_status'] === 'Expired TAC' && filled($row['invite_phone']))
                                        <form method="POST" action="{{ route('teacher.family-login-monitor.invite.send') }}">
                                            @csrf
                                            <input type="hidden" name="family_billing_id" value="{{ $row['family_billing_id'] }}">
                                            <input type="hidden" name="phone" value="{{ $row['invite_phone'] }}">
                                            <button
                                                type="submit"
                                                class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold transition {{ $row['invite_sent_count'] > 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' }}"
                                            >
                                                {{ $row['invite_sent_count'] > 0 ? 'Re-Send Invite' : 'Expired TAC (Stuck)' }}
                                            </button>
                                        </form>
                                    @elseif ($row['tac_status'] === 'Completed')
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ $row['tac_status'] }}</span>
                                    @elseif ($row['tac_status'] === 'No TAC request')
                                        <span class="inline-flex rounded-full border border-zinc-200 bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">{{ $row['tac_status'] }}</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">{{ $row['tac_status'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($row['is_paid'])
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Yes</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">No</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-10 text-center text-zinc-500">No registered family phone data found yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
