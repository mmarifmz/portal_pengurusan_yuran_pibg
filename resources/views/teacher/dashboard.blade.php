<x-layouts::app :title="__('Teacher Dashboard')">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Total Students</p>
                <p class="mt-2 text-3xl font-semibold">{{ $totalStudents }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Total Families</p>
                <p class="mt-2 text-3xl font-semibold">{{ $totalFamilies }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Billed (RM)</p>
                <p class="mt-2 text-3xl font-semibold">{{ number_format($totalBilled, 2) }}</p>
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

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm text-zinc-600 dark:text-zinc-300">Billing year: <span class="font-semibold">{{ $billingYear }}</span> | Paid families: <span class="font-semibold">{{ $paidFamilies }}</span></p>
            <form method="POST" action="{{ route('billing.setup.current-year') }}">
                @csrf
                <input type="hidden" name="billing_year" value="{{ $billingYear }}">
                <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300">
                    Setup/Sync RM100 Family Billing
                </button>
            </form>
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
                    @forelse ($recentBillings as $billing)
                        <tr>
                            <td class="px-6 py-4 font-medium">{{ $billing->family_code }}</td>
                            <td class="px-6 py-4 text-right">{{ number_format($billing->fee_amount, 2) }}</td>
                            <td class="px-6 py-4 text-right">{{ number_format($billing->paid_amount, 2) }}</td>
                            <td class="px-6 py-4 text-right {{ $billing->outstanding_amount > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($billing->outstanding_amount, 2) }}</td>
                            <td class="px-6 py-4">{{ ucfirst($billing->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-zinc-500">No family billing records yet. Click setup/sync first.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>