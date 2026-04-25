<x-layouts::app :title="__('Social Tags')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
                {{ session('status') }}
            </section>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Teacher View</p>
                    <h1 class="text-2xl font-bold text-zinc-900">Social Tags Analytics</h1>
                    <p class="mt-1 text-sm text-zinc-600">Pantau hashtag sosial murid dan kiraan semasa.</p>
                </div>
                <a
                    href="{{ route('teacher.records') }}"
                    class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100"
                >
                    Open Student Directory
                </a>
            </div>

            <form method="GET" action="{{ route('teacher.social-tags.index') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                    Billing Year
                    <select name="billing_year" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        @foreach ($yearOptions as $yearOption)
                            <option value="{{ $yearOption }}" @selected((int) $selectedYear === (int) $yearOption)>{{ $yearOption }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                    Class
                    <select name="class_name" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        <option value="all" @selected($selectedClass === 'all')>All classes</option>
                        @foreach ($availableClasses as $className)
                            <option value="{{ $className }}" @selected($selectedClass === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="sm:col-span-2 lg:col-span-2 flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                        Apply Filter
                    </button>
                    <a href="{{ route('teacher.social-tags.index', ['billing_year' => $selectedYear]) }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        @if ($socialTagLabels === [])
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800 shadow-sm">
                Tiada social tag aktif. Sila tetapkan label social tag di Portal Setting terlebih dahulu.
            </section>
        @else
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">Bulk Tag Student + Siblings</h2>
                        <p class="mt-1 text-xs text-zinc-500">
                            Tampal senarai murid dari spreadsheet. Sistem akan padankan baris yang sepadan, kemudian set tag untuk murid dan semua adik-beradik dalam family code yang sama.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('teacher.social-tags.bulk-apply') }}" class="mt-3 grid gap-3 lg:grid-cols-6">
                    @csrf
                    <input type="hidden" name="billing_year" value="{{ $selectedYear }}">
                    <input type="hidden" name="class_name" value="{{ $selectedClass }}">

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600 lg:col-span-2">
                        Tag to apply
                        <select name="tag_field" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            @foreach ($tagSummaries as $summary)
                                <option value="{{ $summary['field'] }}" @selected(old('tag_field') === $summary['field'])>{{ $summary['hashtag'] }} - {{ $summary['label'] }}</option>
                            @endforeach
                        </select>
                        @error('tag_field')
                            <span class="mt-1 block text-[11px] font-medium text-rose-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600 lg:col-span-4">
                        Paste lines from spreadsheet
                        <textarea
                            name="match_lines"
                            rows="5"
                            placeholder="1[TAB]MUHAMMAD ZAHIR IMAN[TAB]1 ALAMANDA"
                            class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 font-mono text-xs text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        >{{ old('match_lines') }}</textarea>
                        @error('match_lines')
                            <span class="mt-1 block text-[11px] font-medium text-rose-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <div class="lg:col-span-6 flex flex-wrap items-center gap-2">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                            Apply Bulk Tag
                        </button>
                        <p class="text-[11px] text-zinc-500">Cadangan: guna tahun semasa dan class filter yang tepat untuk elak padanan bertindih.</p>
                    </div>
                </form>

                @php($bulkReport = session('bulk_tag_report'))
                @if (is_array($bulkReport))
                    <div class="mt-3 rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-700">
                        <p class="font-semibold text-zinc-900">
                            Laporan bulk tag {{ data_get($bulkReport, 'tag_label', '-') }}:
                            {{ number_format((int) data_get($bulkReport, 'matched_families_count', 0)) }} family dipadankan,
                            {{ number_format((int) data_get($bulkReport, 'updated_students_count', 0)) }} murid dikemas kini.
                        </p>
                        <p class="mt-1">
                            Baris diproses: {{ number_format((int) data_get($bulkReport, 'line_count', 0)) }} |
                            Tidak jumpa: {{ number_format((int) data_get($bulkReport, 'unmatched_count', 0)) }} |
                            Bertindih: {{ number_format((int) data_get($bulkReport, 'ambiguous_count', 0)) }}
                        </p>

                        @php($unmatchedEntries = collect(data_get($bulkReport, 'unmatched_entries', [])))
                        @if ($unmatchedEntries->isNotEmpty())
                            <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3">
                                <p class="font-semibold text-rose-700">Senarai Tidak Jumpa ({{ $unmatchedEntries->count() }})</p>
                                <ul class="mt-2 space-y-1 text-[11px] text-rose-800">
                                    @foreach ($unmatchedEntries as $entry)
                                        <li class="break-words">- {{ $entry }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @php($ambiguousEntries = collect(data_get($bulkReport, 'ambiguous_entries', [])))
                        @if ($ambiguousEntries->isNotEmpty())
                            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <p class="font-semibold text-amber-700">Senarai Bertindih ({{ $ambiguousEntries->count() }})</p>
                                <ul class="mt-2 space-y-1 text-[11px] text-amber-800">
                                    @foreach ($ambiguousEntries as $entry)
                                        <li class="break-words">- {{ $entry }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Total Students</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900">{{ number_format($totalStudents) }}</p>
                    <p class="mt-1 text-xs text-zinc-500">Year {{ $selectedYear }} {{ $selectedClass !== 'all' ? '· '.$selectedClass : '' }}</p>
                </article>

                @foreach ($tagSummaries as $summary)
                    <article class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $summary['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($summary['count']) }}</p>
                        <p class="mt-1 text-xs font-semibold text-emerald-600">{{ $summary['hashtag'] }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ number_format((float) $summary['percent'], 1) }}% of filtered students</p>
                    </article>
                @endforeach
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($tagSummaries as $summary)
                        <a
                            href="{{ route('teacher.social-tags.index', ['billing_year' => $selectedYear, 'class_name' => $selectedClass, 'tag_filter' => $summary['field']]) }}"
                            class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold transition {{ $selectedTagFilter === $summary['field'] ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}"
                        >
                            {{ $summary['hashtag'] }} · {{ $summary['count'] }}
                        </a>
                    @endforeach
                    <a
                        href="{{ route('teacher.social-tags.index', ['billing_year' => $selectedYear, 'class_name' => $selectedClass, 'tag_filter' => 'all']) }}"
                        class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold transition {{ $selectedTagFilter === 'all' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-100' }}"
                    >
                        Show all
                    </a>
                </div>
            </section>

            @if ($selectedTagSummary !== null)
                <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-semibold text-zinc-900">
                                {{ $selectedTagSummary['hashtag'] }} Student List
                            </h2>
                            <p class="mt-1 text-xs text-zinc-500">
                                Paparan murid mengikut group tag terpilih ({{ number_format($filteredTagStudents->count()) }} murid).
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm">
                            <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                <tr>
                                    <th class="px-4 py-3">Family Code</th>
                                    <th class="px-4 py-3">Student</th>
                                    <th class="px-4 py-3">Class</th>
                                    <th class="px-4 py-3 text-right">Profile</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200">
                                @forelse ($filteredTagStudents as $student)
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-zinc-900">{{ $student->family_code ?: '-' }}</td>
                                        <td class="px-4 py-3 text-zinc-700">{{ $student->full_name }}</td>
                                        <td class="px-4 py-3 text-zinc-700">{{ $student->class_name ?: '-' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('teacher.records.family', ['familyCode' => $student->family_code]) }}" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-2 py-1 text-[11px] font-semibold text-zinc-700 transition hover:bg-zinc-100">
                                                Open family
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500">No students found for selected tag and filter.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <h2 class="text-lg font-semibold text-zinc-900">Tag Count by Class</h2>
                <p class="mt-1 text-xs text-zinc-500">Kiraan murid mengikut kelas dan hashtag.</p>

                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm">
                        <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <tr>
                                <th class="px-4 py-3">Class</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                @foreach ($tagSummaries as $summary)
                                    <th class="px-4 py-3 text-right">{{ $summary['hashtag'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @forelse ($classBreakdown as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-zinc-900">{{ $row['class_name'] }}</td>
                                    <td class="px-4 py-3 text-right text-zinc-700">{{ number_format((int) $row['total_students']) }}</td>
                                    @foreach ($tagSummaries as $summary)
                                        <td class="px-4 py-3 text-right text-zinc-700">{{ number_format((int) ($row['tag_counts'][$summary['field']] ?? 0)) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + count($tagSummaries) }}" class="px-4 py-8 text-center text-zinc-500">No student data for selected filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-layouts::app>
