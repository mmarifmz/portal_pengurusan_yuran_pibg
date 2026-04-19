<x-layouts::app :title="__('Student & Family Records')">
    @php
        $allRecordsUrl = route('teacher.records');
        $duplicateRecordsUrl = route('teacher.records', array_filter([
            'record_filter' => 'duplicates',
            'class_name' => $selectedClass ?: null,
            'family_code' => $familyCodeQuery ?: null,
            'student_name' => $studentNameQuery ?: null,
        ]));
        $paidThisYearUrl = route('teacher.records', array_filter([
            'record_filter' => 'paid-this-year',
            'class_name' => $selectedClass ?: null,
            'family_code' => $familyCodeQuery ?: null,
            'student_name' => $studentNameQuery ?: null,
        ]));
        $registeredParentUrl = route('teacher.records', array_filter([
            'record_filter' => 'registered-parent',
            'class_name' => $selectedClass ?: null,
            'family_code' => $familyCodeQuery ?: null,
            'student_name' => $studentNameQuery ?: null,
        ]));
        $paidLastYearUrl = route('teacher.records', array_filter([
            'record_filter' => 'paid-last-year',
            'class_name' => $selectedClass ?: null,
            'family_code' => $familyCodeQuery ?: null,
            'student_name' => $studentNameQuery ?: null,
        ]));
        $paidLastYearLabel = 'Paid last year ('.$lastYear.')';
        $allClassesUrl = route('teacher.records', array_filter([
            'record_filter' => $recordFilter ?: null,
            'family_code' => $familyCodeQuery ?: null,
            'student_name' => $studentNameQuery ?: null,
        ]));
    @endphp

    <div class="space-y-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold text-gray-900">Student &amp; Family Lists {{ $billingYear }}</h1>
            <p class="text-sm text-gray-500">A combined view of every student record and the families currently tracked for {{ $billingYear }}.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Total Students</p>
                <p class="mt-2 text-3xl font-semibold">{{ number_format($studentCount) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Total Families</p>
                <p class="mt-2 text-3xl font-semibold">{{ number_format($familiesCount) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Billed (RM)</p>
                <p class="mt-2 text-3xl font-semibold">{{ number_format($totalBilled, 2) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Collected (RM)</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ number_format($totalCollected, 2) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-zinc-500">Outstanding (RM)</p>
                <p class="mt-2 text-3xl font-semibold text-rose-600">{{ number_format($totalOutstanding, 2) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-zinc-600">Billing year: <span class="font-semibold">{{ $billingYear }}</span> | Paid families: <span class="font-semibold">{{ $familiesPaid }}</span> | Duplicate candidates: <span class="font-semibold">{{ number_format($duplicateCount) }}</span></p>
            <form method="POST" action="{{ route('billing.setup.current-year') }}">
                @csrf
                <input type="hidden" name="billing_year" value="{{ $billingYear }}">
                <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                    Setup/Sync RM100 Family Billing
                </button>
            </form>
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
                <form method="GET" action="{{ route('teacher.records') }}" class="grid gap-3 md:grid-cols-2">
                    @if ($recordFilter !== '')
                        <input type="hidden" name="record_filter" value="{{ $recordFilter }}">
                    @endif
                    @if ($selectedClass !== '')
                        <input type="hidden" name="class_name" value="{{ $selectedClass }}">
                    @endif

                    <label class="text-xs font-semibold text-zinc-600">
                        Search family code
                        <input
                            type="search"
                            name="family_code"
                            value="{{ $familyCodeQuery }}"
                            placeholder="Contoh: SSP-0001"
                            class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>

                    <label class="text-xs font-semibold text-zinc-600">
                        Search student full name
                        <input
                            type="search"
                            name="student_name"
                            value="{{ $studentNameQuery }}"
                            placeholder="Contoh: NUR AISHA"
                            class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                        @if ($studentNameTooShort)
                            <span class="mt-1 block text-[11px] font-medium text-amber-700">Masukkan sekurang-kurangnya 3 aksara untuk carian nama.</span>
                        @else
                            <span class="mt-1 block text-[11px] font-medium text-zinc-500">Minimum 3 aksara untuk carian nama.</span>
                        @endif
                    </label>

                    <div class="md:col-span-2 flex items-center gap-2">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                            Search
                        </button>
                        @if ($familyCodeQuery !== '' || $studentNameQuery !== '')
                            <a href="{{ route('teacher.records', array_filter([
                                'record_filter' => $recordFilter ?: null,
                                'class_name' => $selectedClass ?: null,
                            ])) }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50">
                                Clear search
                            </a>
                        @endif
                    </div>
                </form>

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
                        href="{{ $paidThisYearUrl }}"
                        class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $recordFilter === 'paid-this-year' ? 'border-sky-700 bg-sky-700 text-white shadow-sm ring-2 ring-sky-200' : 'border-sky-300 bg-sky-50 text-sky-800 hover:bg-sky-100' }}"
                    >
                        Paid {{ $billingYear }}
                    </a>
                    <a
                        href="{{ $paidLastYearUrl }}"
                        class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $recordFilter === 'paid-last-year' ? 'border-emerald-700 bg-emerald-700 text-white shadow-sm ring-2 ring-emerald-200' : 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}"
                    >
                        {{ $paidLastYearLabel }}
                    </a>
                    <a
                        href="{{ $registeredParentUrl }}"
                        class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $recordFilter === 'registered-parent' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-50' }}"
                    >
                        Parent is registered
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
                                'family_code' => $familyCodeQuery ?: null,
                                'student_name' => $studentNameQuery ?: null,
                            ])) }}"
                            class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition {{ $selectedClass === $className ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}"
                        >
                            {{ $className }}
                        </a>
                    @endforeach
                </div>

                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
                    Baris dengan latar hijau menandakan keluarga sudah bayar untuk tahun {{ $lastYear }}.
                </div>
                <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-medium text-sky-800">
                    Lencana biru “Paid {{ $billingYear }}” menandakan keluarga sudah bayar untuk tahun semasa.
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
                                @php
                                    $isPaidThisYear = filled($student->family_code) && $paidThisYearFamilyCodes->contains((string) $student->family_code);
                                    $isPaidLastYear = filled($student->family_code) && $paidLastYearFamilyCodes->contains((string) $student->family_code);
                                @endphp
                                <tr class="{{ $isPaidLastYear ? 'bg-emerald-50/70' : '' }}">
                                    <td class="px-5 py-4 font-mono text-xs font-semibold text-zinc-900">{{ $student->student_no }}</td>
                                    <td class="px-5 py-4 text-sm text-zinc-600">
                                        @if ($student->family_code)
                                            <div class="inline-flex items-center gap-2">
                                                <a href="{{ route('teacher.records.family', ['familyCode' => $student->family_code]) }}" class="font-semibold text-emerald-700 underline decoration-transparent transition hover:decoration-current">
                                                    {{ $student->family_code }}
                                                </a>
                                                @if ($isPaidThisYear)
                                                    <span class="inline-flex items-center rounded-full border border-sky-300 bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-800">
                                                        Paid {{ $billingYear }}
                                                    </span>
                                                @endif
                                                @if ($isPaidLastYear)
                                                    <span class="inline-flex items-center rounded-full border border-emerald-300 bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800">
                                                        Paid {{ $lastYear }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-zinc-900">{{ $student->full_name }}</td>
                                    <td class="px-5 py-4 text-sm text-zinc-700">{{ $student->class_name ?: '-' }}</td>
                                    <td class="px-5 py-4 text-sm text-zinc-600">
                                        @php
                                            $parentDisplayName = (string) ($student->resolved_parent_name ?: 'No parent on file');
                                            $needsParentProfileUpdate = preg_match('/^parent\s+ssp-/i', $parentDisplayName) === 1;
                                        @endphp
                                        <p>{{ $parentDisplayName }}</p>
                                        <p class="text-xs text-zinc-400">{{ $student->parent_phone ?: '-' }}</p>
                                        @if ($needsParentProfileUpdate)
                                            <span class="mt-1 inline-flex items-center rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">
                                                Update profile
                                            </span>
                                        @endif
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
