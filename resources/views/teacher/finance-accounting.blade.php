<x-layouts::app :title="__('Finance Accounting Dashboard')">
    <div class="space-y-6">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold text-gray-900">Finance Accounting Dashboard</h1>
            <p class="text-sm text-gray-500">Family-level yuran and sumbangan view for {{ $yearA }} and {{ $yearB }}.</p>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('teacher.finance-accounting') }}" class="grid gap-3 md:grid-cols-4">
                <label class="text-xs font-semibold text-zinc-600">
                    Search
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Family code / name / class"
                        class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <label class="text-xs font-semibold text-zinc-600">
                    Class
                    <select
                        name="class_name"
                        class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    >
                        <option value="">All classes</option>
                        @foreach ($classOptions as $className)
                            <option value="{{ $className }}" @selected($classFilter === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-zinc-600">
                    Yuran/Sumbangan Year A
                    <input
                        type="number"
                        name="year_a"
                        min="2000"
                        max="2100"
                        value="{{ $yearA }}"
                        class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <label class="text-xs font-semibold text-zinc-600">
                    Yuran/Sumbangan Year B
                    <input
                        type="number"
                        name="year_b"
                        min="2000"
                        max="2100"
                        value="{{ $yearB }}"
                        class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                <input type="hidden" name="sort_dir" value="{{ $sortDir }}">

                <div class="md:col-span-4 flex flex-wrap items-center gap-2">
                    <button type="submit" class="rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700">
                        Apply Filters
                    </button>
                    <a
                        href="{{ route('teacher.finance-accounting.export', request()->query()) }}"
                        class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100"
                    >
                        Export CSV (Excel)
                    </a>
                    @if ($search !== '' || $classFilter !== '')
                        <a
                            href="{{ route('teacher.finance-accounting', ['year_a' => $yearA, 'year_b' => $yearB]) }}"
                            class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50"
                        >
                            Clear Filters
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm text-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-5 py-3">Family Code</th>
                            <th class="px-5 py-3">
                                @php
                                    $nextNameDir = $sortBy === 'name' && $sortDir === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <a href="{{ route('teacher.finance-accounting', array_merge(request()->query(), ['sort_by' => 'name', 'sort_dir' => $nextNameDir])) }}" class="inline-flex items-center gap-1 hover:text-zinc-900">
                                    Payer / Parent Name
                                    @if ($sortBy === 'name')
                                        <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </a>
                            </th>
                            <th class="px-5 py-3">Class Name</th>
                            <th class="px-5 py-3 text-right">
                                @if ($currentYear === $yearA)
                                    @php
                                        $nextCurrentDir = $sortBy === 'current_year' && $sortDir === 'asc' ? 'desc' : 'asc';
                                    @endphp
                                    <a href="{{ route('teacher.finance-accounting', array_merge(request()->query(), ['sort_by' => 'current_year', 'sort_dir' => $nextCurrentDir])) }}" class="inline-flex items-center gap-1 hover:text-zinc-900">
                                        Yuran {{ $yearA }}
                                        @if ($sortBy === 'current_year')
                                            <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </a>
                                @else
                                    Yuran {{ $yearA }}
                                @endif
                            </th>
                            <th class="px-5 py-3 text-right">
                                @if ($currentYear === $yearA)
                                    @php
                                        $nextSumbanganDir = $sortBy === 'current_year_sumbangan' && $sortDir === 'asc' ? 'desc' : 'asc';
                                    @endphp
                                    <a href="{{ route('teacher.finance-accounting', array_merge(request()->query(), ['sort_by' => 'current_year_sumbangan', 'sort_dir' => $nextSumbanganDir])) }}" class="inline-flex items-center gap-1 hover:text-zinc-900">
                                        Sumbangan {{ $yearA }}
                                        @if ($sortBy === 'current_year_sumbangan')
                                            <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </a>
                                @else
                                    Sumbangan {{ $yearA }}
                                @endif
                            </th>
                            <th class="px-5 py-3 text-right">
                                @if ($currentYear === $yearB)
                                    @php
                                        $nextCurrentDir = $sortBy === 'current_year' && $sortDir === 'asc' ? 'desc' : 'asc';
                                    @endphp
                                    <a href="{{ route('teacher.finance-accounting', array_merge(request()->query(), ['sort_by' => 'current_year', 'sort_dir' => $nextCurrentDir])) }}" class="inline-flex items-center gap-1 hover:text-zinc-900">
                                        Yuran {{ $yearB }}
                                        @if ($sortBy === 'current_year')
                                            <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </a>
                                @else
                                    Yuran {{ $yearB }}
                                @endif
                            </th>
                            <th class="px-5 py-3 text-right">
                                @if ($currentYear === $yearB)
                                    @php
                                        $nextSumbanganDir = $sortBy === 'current_year_sumbangan' && $sortDir === 'asc' ? 'desc' : 'asc';
                                    @endphp
                                    <a href="{{ route('teacher.finance-accounting', array_merge(request()->query(), ['sort_by' => 'current_year_sumbangan', 'sort_dir' => $nextSumbanganDir])) }}" class="inline-flex items-center gap-1 hover:text-zinc-900">
                                        Sumbangan {{ $yearB }}
                                        @if ($sortBy === 'current_year_sumbangan')
                                            <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </a>
                                @else
                                    Sumbangan {{ $yearB }}
                                @endif
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-5 py-4 text-sm text-zinc-600">
                                    <a href="{{ route('teacher.records.family', ['familyCode' => $row['family_code']]) }}" class="font-semibold text-emerald-700 underline decoration-transparent transition hover:decoration-current">
                                        {{ $row['family_code'] }}
                                    </a>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-zinc-900">{{ $row['name'] }}</p>
                                    @if (!empty($row['students']) && count($row['students']) > 0)
                                        <details class="mt-2 rounded-lg border border-zinc-200 bg-zinc-50/70 px-3 py-2">
                                            <summary class="cursor-pointer text-xs font-semibold text-zinc-600">Lihat murid & kelas ({{ count($row['students']) }})</summary>
                                            <div class="mt-2 space-y-2 text-xs text-zinc-600">
                                                <div class="rounded-md border border-zinc-200 bg-white px-2.5 py-2">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Payer / Parent Name</p>
                                                    <p class="mt-1 font-semibold text-zinc-800">{{ $row['name'] }}</p>
                                                </div>
                                                @foreach ($row['students'] as $student)
                                                    <div class="rounded-md border border-zinc-200 bg-white px-2.5 py-2">
                                                        <p class="font-medium text-zinc-800">{{ $student['full_name'] }}</p>
                                                        <p class="mt-0.5 text-zinc-500">Kelas: {{ $student['class_name'] }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-sm text-zinc-700">{{ $row['class_name'] }}</td>
                                <td class="px-5 py-4 text-right {{ (float) $row["yuran_{$yearA}"] > 0.01 ? "text-blue-700 font-semibold" : "text-zinc-400" }}">RM {{ number_format((float) $row["yuran_{$yearA}"], 2) }}</td>
                                <td class="px-5 py-4 text-right {{ (float) $row["sumbangan_{$yearA}"] > 0.01 ? "text-blue-700 font-semibold" : "text-zinc-400" }}">RM {{ number_format((float) $row["sumbangan_{$yearA}"], 2) }}</td>
                                <td class="px-5 py-4 text-right {{ (float) $row["yuran_{$yearB}"] > 0.01 ? "text-blue-700 font-semibold" : "text-zinc-400" }}">RM {{ number_format((float) $row["yuran_{$yearB}"], 2) }}</td>
                                <td class="px-5 py-4 text-right {{ (float) $row["sumbangan_{$yearB}"] > 0.01 ? "text-blue-700 font-semibold" : "text-zinc-400" }}">RM {{ number_format((float) $row["sumbangan_{$yearB}"], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-sm text-zinc-500">No family records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t-2 border-zinc-300 bg-zinc-50 text-sm font-semibold text-zinc-900">
                        <tr>
                            <td class="px-5 py-3">TOTAL</td>
                            <td class="px-5 py-3"></td>
                            <td class="px-5 py-3"></td>
                            <td class="px-5 py-3 text-right {{ (float) $totals["yuran_{$yearA}"] > 0.01 ? "text-blue-700" : "text-zinc-400" }}">RM {{ number_format((float) $totals["yuran_{$yearA}"], 2) }}</td>
                            <td class="px-5 py-3 text-right {{ (float) $totals["sumbangan_{$yearA}"] > 0.01 ? "text-blue-700" : "text-zinc-400" }}">RM {{ number_format((float) $totals["sumbangan_{$yearA}"], 2) }}</td>
                            <td class="px-5 py-3 text-right {{ (float) $totals["yuran_{$yearB}"] > 0.01 ? "text-blue-700" : "text-zinc-400" }}">RM {{ number_format((float) $totals["yuran_{$yearB}"], 2) }}</td>
                            <td class="px-5 py-3 text-right {{ (float) $totals["sumbangan_{$yearB}"] > 0.01 ? "text-blue-700" : "text-zinc-400" }}">RM {{ number_format((float) $totals["sumbangan_{$yearB}"], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-layouts::app>
