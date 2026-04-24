<x-layouts::app :title="__('Social Tags')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
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
                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            {{ $summary['hashtag'] }} · {{ $summary['count'] }}
                        </span>
                    @endforeach
                </div>
            </section>

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