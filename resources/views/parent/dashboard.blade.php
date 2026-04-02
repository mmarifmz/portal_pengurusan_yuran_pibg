<x-layouts::app :title="__('Parent Dashboard')">
    @php
        $nextOutstandingBilling = $familyBillings->firstWhere(fn ($billing) => $billing->outstanding_amount > 0);
    @endphp
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Total Children Linked</p>
                <p class="mt-2 text-3xl font-semibold">{{ $children->count() }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Family Billings ({{ $billingYear }})</p>
                <p class="mt-2 text-3xl font-semibold">{{ $familyBillings->count() }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Outstanding Family Total (RM)</p>
                <p class="mt-2 text-3xl font-semibold {{ $totalOutstanding > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($totalOutstanding, 2) }}</p>
                @if ($nextOutstandingBilling)
                    <div class="mt-4">
                        <a href="{{ route('parent.payments.checkout', $nextOutstandingBilling) }}"
                        class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-emerald-700">
                            {{ __('Bayar Keluarga') }} {{ $nextOutstandingBilling->family_code }}
                        </a>
                    </div>
                @endif
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
                        <th class="px-6 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        @forelse ($familyBillings as $billing)
                            <tr>
                                <td class="px-6 py-4 font-medium">{{ $billing->family_code }}</td>
                                <td class="px-6 py-4 text-right">{{ number_format($billing->fee_amount, 2) }}</td>
                                <td class="px-6 py-4 text-right">{{ number_format($billing->paid_amount, 2) }}</td>
                                <td class="px-6 py-4 text-right {{ $billing->outstanding_amount > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($billing->outstanding_amount, 2) }}</td>
                                <td class="px-6 py-4">{{ ucfirst($billing->status) }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if($billing->outstanding_amount > 0)
                                        <a href="{{ route('parent.payments.checkout', $billing) }}" class="rounded-lg bg-[color:var(--brand-green, #2f7a55)] px-3 py-1 text-xs font-semibold text-white hover:opacity-90">Bayar</a>
                                    @else
                                        <span class="text-xs text-zinc-500">Lengkap</span>
                                    @endif
                                </td>
                            </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-zinc-500">
                                No family billing found yet. Please contact school admin.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <th class="px-6 py-3">Student No</th>
                        <th class="px-6 py-3">Family Code</th>
                        <th class="px-6 py-3">Name</th>
                        <th class="px-6 py-3">Class</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    @forelse ($children as $student)
                        <tr>
                            <td class="px-6 py-4 font-medium">{{ $student->student_no }}</td>
                            <td class="px-6 py-4">{{ $student->family_code ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $student->full_name }}</td>
                            <td class="px-6 py-4">{{ $student->class_name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-zinc-500">No children linked to your phone yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>
