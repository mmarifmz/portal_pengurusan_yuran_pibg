<x-layouts::app :title="__('Student & Family Records')">
    @php
        $allRecordsUrl = route('teacher.records');
        $duplicateRecordsUrl = route('teacher.records', array_filter([
            'record_filter' => 'duplicates',
            'class_name' => $selectedClass ?: null,
        ]));
        $withoutFamilyUrl = route('teacher.records', array_filter([
            'record_filter' => 'without-family',
            'class_name' => $selectedClass ?: null,
        ]));
        $allClassesUrl = route('teacher.records', array_filter([
            'record_filter' => $recordFilter ?: null,
        ]));
    @endphp

    <div class="space-y-8">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Billing Intelligence</p>
            <h1 class="text-2xl font-bold text-gray-900">Student &amp; Family Lists {{ $billingYear }}</h1>
            <p class="text-sm text-gray-500">A combined view of every student record and the families currently tracked for {{ $billingYear }}.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Yuran Collection</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-600">RM {{ number_format($yuranCollection, 2) }}</p>
                <p class="mt-1 text-xs text-zinc-500">Jumlah kutipan yuran berjaya direkodkan.</p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Sumbangan Collection</p>
                <p class="mt-2 text-3xl font-semibold text-sky-600">RM {{ number_format($sumbanganCollection, 2) }}</p>
                <p class="mt-1 text-xs text-zinc-500">Jumlah sumbangan tambahan daripada transaksi berjaya.</p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Registered Parent</p>
                <p class="mt-2 text-3xl font-semibold text-zinc-900">{{ number_format($registeredParentCount) }}</p>
                <p class="mt-1 text-xs text-zinc-500">Bilangan parent yang berjaya log masuk menggunakan TAC.</p>
            </div>

            <a href="{{ $duplicateRecordsUrl }}" class="block rounded-2xl border {{ $recordFilter === 'duplicates' ? 'border-amber-300 ring-2 ring-amber-100' : 'border-amber-200' }} bg-amber-50 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:col-span-2 lg:col-span-3">
                <p class="text-sm text-amber-700">Duplicate candidates</p>
                <p class="mt-2 text-3xl font-semibold text-amber-800">{{ number_format($duplicateCount) }}</p>
                <p class="mt-1 text-xs text-amber-700">Review before deleting any duplicate record</p>
            </a>
        </div>

        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Full Student Directory</h2>
                    <p class="text-sm text-gray-500">Sorted by family code, then name.</p>
                </div>
                <span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $students->count() }} students</span>
            </div>

            <div class="space-y-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="{{ $allRecordsUrl }}"
                        class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $recordFilter === '' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:border-emerald-300 hover:text-emerald-700' }}"
                    >
                        All records
                    </a>
                    <a
                        href="{{ $duplicateRecordsUrl }}"
                        class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $recordFilter === 'duplicates' ? 'border-amber-600 bg-amber-500 text-white' : 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100' }}"
                    >
                        Duplicate only
                    </a>
                    <a
                        href="{{ $withoutFamilyUrl }}"
                        class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $recordFilter === 'without-family' ? 'border-rose-600 bg-rose-600 text-white' : 'border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100' }}"
                    >
                        Missing family code
                    </a>
                    @if ($filtersActive)
                        <a
                            href="{{ $allRecordsUrl }}"
                            class="inline-flex items-center rounded-full border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50"
                        >
                            Clear filters
                        </a>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">By class</span>
                    <a
                        href="{{ $allClassesUrl }}"
                        class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $selectedClass === '' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:border-emerald-300 hover:text-emerald-700' }}"
                    >
                        All classes
                    </a>
                    @foreach ($availableClasses as $className)
                        <a
                            href="{{ route('teacher.records', array_filter([
                                'record_filter' => $recordFilter ?: null,
                                'class_name' => $className,
                            ])) }}"
                            class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $selectedClass === $className ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}"
                        >
                            {{ $className }}
                        </a>
                    @endforeach
                </div>
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
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 bg-white">
                            @forelse ($students as $student)
                                <tr>
                                    <td class="px-5 py-4 font-mono text-xs font-semibold text-zinc-900">{{ $student->student_no }}</td>
                                    <td class="px-5 py-4 text-sm text-zinc-600">{{ $student->family_code ?: '-' }}</td>
                                    <td class="px-5 py-4 font-semibold text-zinc-900">{{ $student->full_name }}</td>
                                    <td class="px-5 py-4 text-sm text-zinc-700">{{ $student->class_name ?: '-' }}</td>
                                    <td class="px-5 py-4 text-sm text-zinc-600">
                                        <p>{{ $student->parent_name ?: 'No parent on file' }}</p>
                                        <p class="text-xs text-zinc-400">{{ $student->parent_phone ?: '-' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-right font-semibold {{ $student->outstanding_balance > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                        RM {{ number_format($student->outstanding_balance, 2) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium {{ $student->status === 'active' ? 'text-emerald-600' : 'text-amber-600' }}">
                                                {{ ucfirst($student->status) }}
                                            </span>
                                            @if ($student->is_duplicate)
                                                <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-amber-700">
                                                    Duplicate
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        @if ($student->is_duplicate)
                                            <a
                                                href="{{ route('teacher.records.duplicates.review', $student) }}"
                                                class="inline-flex items-center rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-100"
                                            >
                                                Review duplicate
                                            </a>
                                        @else
                                            <span class="text-xs text-zinc-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-8 text-center text-sm text-zinc-500">
                                        No students match the current filter.
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
