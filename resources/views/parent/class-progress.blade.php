<x-layouts::app :title="__('Ranking Kutipan Yuran PIBG')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500">Parent View</p>
                    <h1 class="mt-2 text-2xl font-bold text-zinc-900">Ranking Kutipan Yuran PIBG</h1>
                    <p class="mt-1 text-sm text-zinc-600">Setiap Sumbangan Membina Masa Depan Anak-anak Kita</p>
                </div>
            </div>
            <p class="mt-4 text-sm font-medium text-zinc-600">Paparan: {{ $selectedWeekLabel }}</p>
        </section>

        <section class="space-y-6">
            @foreach (($classProgressByTahap ?? collect()) as $tahapName => $rows)
                <div>
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-[0.14em] text-zinc-600">{{ $tahapName }}</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        @forelse ($rows as $index => $row)
                            <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <h3 class="flex items-center gap-2 text-xl font-bold text-zinc-900">
                                        @if ($index === 0)
                                            <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">&#x1F947;</span>
                                        @elseif ($index === 1)
                                            <span class="inline-flex items-center rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-bold text-zinc-700">&#x1F948;</span>
                                        @elseif ($index === 2)
                                            <span class="inline-flex items-center rounded-md bg-orange-100 px-2 py-0.5 text-xs font-bold text-orange-700">&#x1F949;</span>
                                        @endif
                                        <span class="inline-flex min-w-[2.4rem] justify-center rounded-md bg-zinc-100 px-2 py-0.5 text-base font-extrabold text-zinc-700">#{{ $index + 1 }}</span>
                                        <span>{{ $row['class_name'] }}</span>
                                    </h3>
                                    <p class="text-3xl font-extrabold text-emerald-700">{{ number_format((float) $row['percentage'], 0) }}%</p>
                                </div>
                                <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-zinc-200">
                                    <div class="flex h-3 w-full">
                                        <div class="bg-emerald-500" style="width: {{ max(0, min(100, $row['percentage'])) }}%;"></div>
                                        <div class="bg-zinc-300" style="width: {{ 100 - max(0, min(100, $row['percentage'])) }}%;"></div>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-6 text-sm text-zinc-500 md:col-span-2">
                                Tiada data kutipan kelas untuk {{ $tahapName }}.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </section>
    </div>
</x-layouts::app>
