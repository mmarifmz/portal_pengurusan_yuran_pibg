<x-layouts::app :title="__('Class Payment Progress')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Teacher View</p>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900">Progress Bayaran Yuran Mengikut Kelas</h1>
                    <p class="mt-1 text-sm text-zinc-600">Tahun bil {{ $billingYear }} · Interaktif tanpa reload untuk tapisan dan paparan lanjut.</p>
                </div>
                <div class="w-full sm:w-auto">
                    <label class="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                        Tapis Tahun
                        <select id="yearLevelFilter" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 sm:w-52">
                            <option value="all">Semua Tahun</option>
                            @foreach ($yearLevelOptions as $yearLevel)
                                <option value="{{ $yearLevel }}">Tahun {{ $yearLevel }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </div>
        </section>

        <section id="classProgressList" class="grid gap-4 lg:grid-cols-2">
            @foreach ($progressByClass as $index => $row)
                @php
                    $progressValue = (float) ($row['progress_percent'] ?? 0);
                    $safeProgress = max(0, min(100, $progressValue));
                @endphp
                <article
                    class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm"
                    data-class-card="1"
                    data-year-level="{{ $row['year_level'] ?? 'other' }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold text-zinc-900">{{ $row['class_name'] }}</h2>
                            <p class="mt-1 text-xs text-zinc-500">{{ $row['paid_count'] }} / {{ $row['total_students'] }} murid telah menjelaskan</p>
                        </div>
                        @if (! empty($row['teacher_whatsapp_url']))
                            <a
                                href="{{ $row['teacher_whatsapp_url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex shrink-0 items-center rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                            >
                                Send to class teacher (Whatsapp)
                            </a>
                        @else
                            <span class="inline-flex shrink-0 items-center rounded-xl border border-zinc-200 bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-500">Tiada nombor guru</span>
                        @endif
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <div class="h-3 flex-1 overflow-hidden rounded-full bg-zinc-200">
                            <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" style="width: {{ $safeProgress }}%"></div>
                        </div>
                        <span class="text-xs font-semibold text-zinc-700">{{ number_format($safeProgress, 1) }}%</span>
                        <button
                            type="button"
                            class="js-toggle-details inline-flex items-center rounded-xl border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-100"
                            data-target-id="class-progress-details-{{ $index }}"
                            aria-expanded="false"
                        >
                            Expand
                        </button>
                    </div>

                    <div id="class-progress-details-{{ $index }}" class="mt-4 hidden rounded-xl border border-zinc-200 bg-zinc-50 p-3">
                        <div class="mb-3 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Murid belum menjelaskan Yuran</p>
                                <p class="mt-1 text-sm font-bold text-rose-700">{{ $row['unpaid_count'] }} out of {{ $row['total_students'] }} total</p>
                            </div>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Murid telah menjelaskan Yuran</p>
                                <p class="mt-1 text-sm font-bold text-emerald-700">{{ $row['paid_count'] }} out of {{ $row['total_students'] }} total</p>
                            </div>
                        </div>

                        <div class="grid gap-3 lg:grid-cols-2">
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-rose-700">Belum jelas ({{ $row['unpaid_count'] }})</h3>
                                <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-rose-100 bg-white p-2 text-sm text-zinc-700">
                                    @forelse ($row['unpaid_students'] as $studentName)
                                        <li>{{ $studentName }}</li>
                                    @empty
                                        <li class="text-zinc-500">Tiada murid.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Sudah jelas ({{ $row['paid_count'] }})</h3>
                                <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-emerald-100 bg-white p-2 text-sm text-zinc-700">
                                    @forelse ($row['paid_students'] as $studentName)
                                        <li>{{ $studentName }}</li>
                                    @empty
                                        <li class="text-zinc-500">Tiada murid.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <div id="classProgressEmpty" class="hidden rounded-2xl border border-zinc-200 bg-white p-6 text-center text-sm text-zinc-500 shadow-sm">
            Tiada kelas untuk tapisan ini.
        </div>
    </div>

    <script>
        (function () {
            const filter = document.getElementById('yearLevelFilter');
            const cards = Array.from(document.querySelectorAll('[data-class-card="1"]'));
            const emptyState = document.getElementById('classProgressEmpty');

            const applyFilter = () => {
                const value = filter?.value || 'all';
                let visibleCount = 0;

                cards.forEach((card) => {
                    const level = card.getAttribute('data-year-level') || 'other';
                    const visible = value === 'all' || value === level;
                    card.classList.toggle('hidden', !visible);
                    if (visible) {
                        visibleCount += 1;
                    }
                });

                if (emptyState) {
                    emptyState.classList.toggle('hidden', visibleCount > 0);
                }
            };

            const bindExpandButtons = () => {
                const buttons = Array.from(document.querySelectorAll('.js-toggle-details'));

                buttons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const targetId = button.getAttribute('data-target-id');
                        if (!targetId) {
                            return;
                        }

                        const panel = document.getElementById(targetId);
                        if (!panel) {
                            return;
                        }

                        const isHidden = panel.classList.contains('hidden');
                        panel.classList.toggle('hidden', !isHidden);
                        button.textContent = isHidden ? 'Collapse' : 'Expand';
                        button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                    });
                });
            };

            filter?.addEventListener('change', applyFilter);
            bindExpandButtons();
            applyFilter();
        }());
    </script>
</x-layouts::app>