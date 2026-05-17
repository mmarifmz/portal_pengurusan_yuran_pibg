<x-layouts::app :title="__('Class Payment Progress')">
    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Teacher View</p>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900">Leaderboard Bayaran Mengikut Kelas</h1>
                    <p class="mt-1 text-sm text-zinc-600">Bagi Sesi {{ $billingYear }}</p>
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

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm text-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-5 py-3">Kelas</th>
                            <th class="px-5 py-3 text-right">Jumlah Keluarga</th>
                            <th class="px-5 py-3 text-right">Selesai Bayar</th>
                            <th class="px-5 py-3 text-right">Bayaran Sebahagian</th>
                            <th class="px-5 py-3 text-right">Belum Bayar</th>
                            <th class="px-5 py-3 text-right">Kutipan Yuran</th>
                            <th class="px-5 py-3 text-right">Sumbangan Tambahan</th>
                            <th class="px-5 py-3 text-right">Jumlah Kutipan</th>
                            <th class="px-5 py-3 text-right">Baki Tertunggak</th>
                            <th class="px-5 py-3 text-right">Completion %</th>
                            <th class="px-5 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @forelse ($leaderboardRows as $row)
                            <tr data-class-card="1" data-year-level="{{ $row['year_level'] ?? 'other' }}">
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-zinc-900">{{ $row['class_name'] }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $row['teacher_name'] }}</p>
                                </td>
                                <td class="px-5 py-4 text-right font-semibold text-zinc-900">{{ $row['total_families'] }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-emerald-700">{{ $row['fully_paid_families'] }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-amber-600">{{ $row['partial_paid_families'] }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-rose-600">{{ $row['unpaid_families'] }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-emerald-700">RM {{ number_format((float) $row['yuran_collected'], 2) }}</td>
                                <td class="px-5 py-4 text-right {{ (float) $row['sumbangan_tambahan_collected'] > 0 ? 'font-semibold text-cyan-700' : 'text-zinc-400' }}">RM {{ number_format((float) $row['sumbangan_tambahan_collected'], 2) }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-zinc-900">RM {{ number_format((float) $row['jumlah_kutipan'], 2) }}</td>
                                <td class="px-5 py-4 text-right {{ (float) $row['baki_tertunggak'] > 0 ? 'font-semibold text-amber-700' : 'font-semibold text-emerald-700' }}">RM {{ number_format((float) $row['baki_tertunggak'], 2) }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-zinc-900">{{ number_format((float) $row['completion_percent'], 2) }}%</td>
                                <td class="px-5 py-4 text-right">
                                    @if (! empty($row['teacher_whatsapp_url']))
                                        <a
                                            href="{{ $row['teacher_whatsapp_url'] }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex shrink-0 items-center rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                        >
                                            WhatsApp Guru
                                        </a>
                                    @else
                                        <span class="text-xs text-zinc-400">Tiada nombor</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-5 py-8 text-center text-sm text-zinc-500">Tiada data kelas untuk sesi ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div id="classProgressEmpty" class="hidden rounded-2xl border border-zinc-200 bg-white p-6 text-center text-sm text-zinc-500 shadow-sm">
            Tiada kelas untuk tapisan ini.
        </div>
    </div>

    <script>
        (function () {
            const filter = document.getElementById('yearLevelFilter');
            const rows = Array.from(document.querySelectorAll('[data-class-card="1"]'));
            const emptyState = document.getElementById('classProgressEmpty');

            const applyFilter = () => {
                const value = filter?.value || 'all';
                let visibleCount = 0;

                rows.forEach((row) => {
                    const level = row.getAttribute('data-year-level') || 'other';
                    const visible = value === 'all' || value === level;
                    row.classList.toggle('hidden', !visible);
                    if (visible) {
                        visibleCount += 1;
                    }
                });

                if (emptyState) {
                    emptyState.classList.toggle('hidden', visibleCount > 0);
                }
            };

            filter?.addEventListener('change', applyFilter);
            applyFilter();
        }());
    </script>
</x-layouts::app>
