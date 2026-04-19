<x-layouts::app :title="__('Year Reconcile')">
    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Data operations</p>
                <h1 class="text-2xl font-bold text-zinc-900">Year Reconcile</h1>
                <p class="text-sm text-zinc-500">Load past-year and current-year CSV, preview impact, then apply safely.</p>
            </div>
            <a href="{{ route('teacher.records') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50">
                Back to Student &amp; Family Lists
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">1) Reconcile Preview</h2>
            <p class="text-xs text-zinc-500">Upload both files first. No data changes happen in preview.</p>

            <form method="POST" action="{{ route('teacher.reconcile.preview') }}" enctype="multipart/form-data" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <label class="text-xs font-semibold text-zinc-600">
                    Past year CSV (example: 2025 fees history)
                    <input type="file" name="past_year_csv" accept=".csv,.txt" required class="mt-1 block w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800" />
                </label>

                <label class="text-xs font-semibold text-zinc-600">
                    Current year student CSV
                    <input type="file" name="current_year_csv" accept=".csv,.txt" required class="mt-1 block w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800" />
                </label>

                <label class="text-xs font-semibold text-zinc-600">
                    School code
                    <input type="text" name="school_code" value="{{ old('school_code', 'SSP') }}" maxlength="6" class="mt-1 block w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800" />
                </label>

                <label class="text-xs font-semibold text-zinc-600">
                    Current year
                    <input type="number" name="current_year" value="{{ old('current_year', now()->year) }}" min="2020" max="2100" required class="mt-1 block w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800" />
                </label>

                <div class="md:col-span-2">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Generate dry-run preview
                    </button>
                </div>
            </form>
        </section>

        @if (is_array($preview))
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">2) Dry-run Summary</h2>
                        <p class="text-xs text-zinc-500">Review this before apply.</p>
                    </div>
                    <form method="POST" action="{{ route('teacher.reconcile.apply') }}">
                        @csrf
                        <input type="hidden" name="preview_token" value="{{ $previewToken }}">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500">
                            Apply reconcile now
                        </button>
                    </form>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Past rows</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900">{{ number_format((int) ($preview['past_rows_count'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Current rows</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900">{{ number_format((int) ($preview['current_rows_count'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Will create</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900">{{ number_format((int) ($preview['to_create_count'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Will update</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900">{{ number_format((int) ($preview['to_update_count'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Class 6 leavers</p>
                        <p class="mt-1 text-xl font-semibold text-amber-700">{{ number_format((int) ($preview['leaver_count'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Apply year</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900">{{ (int) ($preview['current_year'] ?? now()->year) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Paid history rows (past CSV)</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900">{{ number_format((int) ($preview['paid_history_rows_count'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Importable paid history</p>
                        <p class="mt-1 text-xl font-semibold text-emerald-700">{{ number_format((int) ($preview['importable_paid_history_count'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Unmatched paid history</p>
                        <p class="mt-1 text-xl font-semibold text-rose-700">{{ number_format((int) ($preview['unmatched_paid_history_count'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Importable paid total (RM)</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900">{{ number_format((float) ($preview['paid_history_amount_total'] ?? 0), 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Importable sumbangan total (RM)</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900">{{ number_format((float) ($preview['paid_history_donation_total'] ?? 0), 2) }}</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm">
                        <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <tr>
                                <th class="px-4 py-3">Leaver candidate (class 6)</th>
                                <th class="px-4 py-3">Family code</th>
                                <th class="px-4 py-3">Class</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @forelse (($preview['leaver_rows'] ?? []) as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-zinc-900">{{ $row['full_name'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-zinc-700">{{ $row['family_code'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-zinc-700">{{ $row['class_name'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-4 text-center text-zinc-500">No class-6 leaver candidates from preview.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm">
                        <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <tr>
                                <th class="px-4 py-3">Unmatched paid row (not in current roster)</th>
                                <th class="px-4 py-3">Family code</th>
                                <th class="px-4 py-3">Ref</th>
                                <th class="px-4 py-3 text-right">Amount paid (RM)</th>
                                <th class="px-4 py-3">Paid at</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @forelse (($preview['unmatched_paid_history_rows'] ?? []) as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-zinc-900">{{ $row['full_name'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-zinc-700">{{ $row['family_code'] ?? '-' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-zinc-700">{{ $row['payment_reference'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right text-zinc-700">{{ number_format((float) ($row['amount_paid'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-zinc-700">{{ $row['paid_at'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-zinc-500">No unmatched paid history rows.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-layouts::app>
