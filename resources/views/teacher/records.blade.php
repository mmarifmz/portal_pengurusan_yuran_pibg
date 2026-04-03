<x-layouts::app :title="__('Student & Family Records')">
    <div class="space-y-8">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Billing Intelligence</p>
            <h1 class="text-2xl font-bold text-gray-900">Student &amp; Family Lists {{ $billingYear }}</h1>
            <p class="text-sm text-gray-500">A combined view of every student record and the families currently tracked for {{ $billingYear }}.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Registered Students</p>
                <p class="mt-2 text-3xl font-semibold text-zinc-900">{{ number_format($studentCount) }}</p>
                <p class="text-xs text-zinc-500 mt-1">{{ $studentsWithoutFamily }} students still need a family code</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Tracked Families</p>
                <p class="mt-2 text-3xl font-semibold text-zinc-900">{{ number_format($familiesCount) }}</p>
                <p class="text-xs text-zinc-500 mt-1">{{ $familiesPaid }} fully paid families</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Total Collected</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-600">RM {{ number_format($totalCollected, 2) }}</p>
                <p class="text-xs text-zinc-500 mt-1">/ RM {{ number_format($totalBilled, 2) }} billed</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Outstanding (RM)</p>
                <p class="mt-2 text-3xl font-semibold text-rose-600">RM {{ number_format($totalOutstanding, 2) }}</p>
                <p class="text-xs text-zinc-500 mt-1">{{ $familiesCount ? number_format(($totalOutstanding / max($totalCollected, 1)) * 100, 1) : 0 }}% still pending</p>
            </div>
        </div>

        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Full Student Directory</h2>
                    <p class="text-sm text-gray-500">Sorted by family code, then name.</p>
                </div>
                <span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $students->count() }} students</span>
            </div>

            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm text-zinc-700">
                        <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <tr>
                                <th class="px-5 py-3">Student No</th>
                                <th class="px-5 py-3">Family Code</th>
                                <th class="px-5 py-3">Name</th>
                                <th class="px-5 py-3">Class</th>
                                <th class="px-5 py-3">Parent</th>
                                <th class="px-5 py-3 text-right">Balance (RM)</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 bg-white">
                            @forelse ($students as $student)
                                <tr>
                                    <td class="px-5 py-4 font-mono text-xs font-semibold text-zinc-900">{{ $student->student_no }}</td>
                                    <td class="px-5 py-4 text-sm text-zinc-600">{{ $student->family_code ?? '�' }}</td>
                                    <td class="px-5 py-4 font-semibold text-zinc-900">{{ $student->full_name }}</td>
                                    <td class="px-5 py-4 text-sm text-zinc-700">{{ $student->class_name ?? '�' }}</td>
                                    <td class="px-5 py-4 text-sm text-zinc-600">
                                        <p>{{ $student->parent_name ?? 'No parent on file' }}</p>
                                        <p class="text-xs text-zinc-400">{{ $student->parent_phone ?? '�' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-right font-semibold {{ $student->outstanding_balance > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                        RM {{ number_format($student->outstanding_balance, 2) }}
                                    </td>
                                    <td class="px-5 py-4 text-sm font-medium {{ $student->status === 'active' ? 'text-emerald-600' : 'text-amber-600' }}">
                                        {{ ucfirst($student->status) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-8 text-center text-sm text-zinc-500">
                                        No students found yet. Use the import tool to seed the list.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Family Billing Registry</h2>
                    <p class="text-sm text-gray-500">{{ $billingYear }} ledger showing fees, payments and outstanding balances.</p>
                </div>
                <span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $familyBillings->count() }} families</span>
            </div>

            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm text-zinc-700">
                        <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <tr>
                                <th class="px-5 py-3">Family Code</th>
                                <th class="px-5 py-3">Students</th>
                                <th class="px-5 py-3 text-right">Fee (RM)</th>
                                <th class="px-5 py-3 text-right">Paid (RM)</th>
                                <th class="px-5 py-3 text-right">Outstanding (RM)</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 bg-white">
                            @forelse ($familyBillings as $billing)
                                <tr>
                                    <td class="px-5 py-4 font-medium text-zinc-900">{{ $billing->family_code }}</td>
                                    <td class="px-5 py-4 text-sm text-zinc-600">{{ $billing->students_count ?? 0 }}</td>
                                    <td class="px-5 py-4 text-right">{{ number_format($billing->fee_amount, 2) }}</td>
                                    <td class="px-5 py-4 text-right">{{ number_format($billing->paid_amount, 2) }}</td>
                                    <td class="px-5 py-4 text-right font-semibold {{ $billing->outstanding_amount > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                        {{ number_format($billing->outstanding_amount, 2) }}
                                    </td>
                                    <td class="px-5 py-4 text-sm font-medium text-zinc-600">{{ ucfirst($billing->status) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-sm text-zinc-500">
                                        No family billings defined for {{ $billingYear }} yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-layouts::app>
