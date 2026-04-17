<x-layouts::app :title="__('Family Login Monitor')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white/90 p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900">Registered Family Login Monitor</h1>
                    <p class="mt-1 text-sm text-zinc-600">Monitor family phone registrations and parent login activity.</p>
                </div>
                <p class="text-xs font-medium text-zinc-500">Generated: {{ $generatedAt->format('d M Y H:i') }}</p>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Registered Families</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($totalFamilies) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Total Logins</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($totalLoginCount) }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Paid Families</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($rows->where('is_paid', true)->count()) }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white/90 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">
                            <th class="px-4 py-3">Family Code</th>
                            <th class="px-4 py-3">Phone Number</th>
                            <th class="px-4 py-3 text-right">Login Count</th>
                            <th class="px-4 py-3">Latest Login Timestamp</th>
                            <th class="px-4 py-3">Yuran Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm text-zinc-800">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-zinc-900">{{ $row['family_code'] }}</td>
                                <td class="px-4 py-3">{{ $row['phones_display'] !== '' ? $row['phones_display'] : '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['login_count']) }}</td>
                                <td class="px-4 py-3">{{ $row['latest_login_at'] ? $row['latest_login_at']->format('d M Y H:i:s') : '-' }}</td>
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
                                <td colspan="5" class="px-4 py-10 text-center text-zinc-500">No registered family phone data found yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>