<x-layouts::app :title="__('PTA Billing Dashboard')">
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Billing Year</p>
                <p class="mt-2 text-3xl font-semibold">{{ $billingYear }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Total Families</p>
                <p class="mt-2 text-3xl font-semibold">{{ $totalFamilies }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Collected (RM)</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ number_format($totalCollected, 2) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Outstanding (RM)</p>
                <p class="mt-2 text-3xl font-semibold text-rose-600">{{ number_format($totalOutstanding, 2) }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <th class="px-6 py-3">Family Code</th>
                        <th class="px-6 py-3 text-right">Fee (RM)</th>
                        <th class="px-6 py-3 text-right">Paid (RM)</th>
                        <th class="px-6 py-3 text-right">Outstanding (RM)</th>
                        <th class="px-6 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @forelse ($billings as $billing)
                        <tr>
                            <td class="px-6 py-4 font-medium">{{ $billing->family_code }}</td>
                            <td class="px-6 py-4 text-right">{{ number_format($billing->fee_amount, 2) }}</td>
                            <td class="px-6 py-4 text-right">{{ number_format($billing->paid_amount, 2) }}</td>
                            <td class="px-6 py-4 text-right {{ $billing->outstanding_amount > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($billing->outstanding_amount, 2) }}</td>
                            <td class="px-6 py-4">{{ ucfirst($billing->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-zinc-500">No billing records yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.calendar-manager', ['calendarEvents' => $calendarEvents])
    </div>
</x-layouts::app>
