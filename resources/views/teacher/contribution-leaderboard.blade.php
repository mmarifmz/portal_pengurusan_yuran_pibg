<x-layouts::app :title="__('Leaderboard Sumbangan PIBG')">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-zinc-200 p-6 text-white shadow-xl" style="background: linear-gradient(135deg, #0e7490 0%, #0f766e 45%, #047857 100%);">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-100">Teacher / Admin View</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Leaderboard Sumbangan PIBG</h1>
            <p class="mt-2 text-sm text-emerald-50">
                Prestasi kelas dari {{ $competitionStart->format('d M Y') }} hingga {{ now()->format('d M Y') }} | Tahun bil {{ $billingYear }}
            </p>
            <p class="mt-1 text-xs text-emerald-100">
                Dua metrik dipaparkan: <strong>Tanpa Sumbangan Tambahan</strong> dan <strong>Termasuk Sumbangan Tambahan</strong>.
            </p>
        </section>

        @foreach ($groupedByTahap as $tahap => $rows)
            @php
                $top = $rows->take(3)->values();
                $maxPercent = max(1, (float) $top->max('without_donation_percent'));
                $barColors = ['#047857', '#0891b2', '#f43f5e'];
            @endphp

            <section class="overflow-hidden rounded-3xl border border-zinc-200 shadow-lg" style="background:#fde86b;">
                <div class="border-b border-black/10 px-5 py-4 text-white" style="background:#365c66;">
                    <h2 class="text-2xl font-black tracking-wide">{{ $tahap }}</h2>
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-cyan-100">{{ $tahap === 'Tahap 1' ? 'Tahun 1,2,3 untuk Tahap 1' : 'Tahun 4,5,6 untuk Tahap 2' }}</p>
                </div>

                <div class="p-5">
                    <div class="grid gap-4 lg:grid-cols-3">
                        @forelse($top as $index => $row)
                            @php
                                $height = (float) $row['without_donation_percent'] > 0
                                    ? max(28, min(100, (($row['without_donation_percent'] / $maxPercent) * 100)))
                                    : 28;
                            @endphp
                            <article class="rounded-2xl border border-black/10 bg-white/70 p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-lg font-black text-zinc-900">#{{ $index + 1 }}</p>
                                    <span class="rounded-full bg-black/10 px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-zinc-800">{{ $tahap }}</span>
                                </div>
                                <p class="mt-1 text-2xl font-black uppercase tracking-wide text-zinc-900">{{ $row['class_name'] }}</p>

                                <div class="mt-4 rounded-xl border border-black/10 bg-zinc-100 p-2">
                                    <div class="h-6 overflow-hidden rounded-lg bg-white/70">
                                        <div
                                            class="h-full rounded-lg"
                                            style="width: {{ max(6, min(100, (float) $row['without_donation_percent'])) }}%; background: {{ $barColors[$index] ?? '#047857' }};"
                                        ></div>
                                    </div>
                                </div>

                                <div class="mt-3 space-y-1 text-sm font-semibold text-zinc-800">
                                    <p>Tanpa sumbangan: <span class="text-base font-black">{{ number_format((float) $row['without_donation_percent'], 1) }}%</span></p>
                                    <p class="text-xs text-zinc-700">RM {{ number_format((float) $row['without_donation_total'], 2) }}</p>
                                    <p>Termasuk sumbangan: <span class="text-base font-black">{{ number_format((float) $row['with_donation_percent'], 1) }}%</span></p>
                                    <p class="text-xs text-zinc-700">RM {{ number_format((float) $row['with_donation_total'], 2) }}</p>
                                </div>
                            </article>
                        @empty
                            <div class="col-span-full rounded-2xl border border-zinc-300 bg-white px-4 py-5 text-sm text-zinc-600">Tiada data untuk {{ $tahap }}.</div>
                        @endforelse
                    </div>

                    @if($rows->count() > 3)
                        <div class="mt-5 rounded-2xl border border-black/10 bg-white/65 p-4">
                            <h3 class="text-sm font-black uppercase tracking-wide text-zinc-800">Kedudukan Lain</h3>
                            <div class="mt-3 grid gap-2 md:grid-cols-2">
                                @foreach($rows->slice(3) as $i => $row)
                                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
                                        <span class="font-bold text-zinc-900">#{{ $i + 4 }} {{ $row['class_name'] }}</span>
                                        <span class="font-extrabold text-emerald-700">{{ number_format((float) $row['without_donation_percent'], 1) }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</x-layouts::app>