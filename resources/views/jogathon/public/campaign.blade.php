@php
    use App\Support\JogathonAmount;

    $title = 'Larian Sihat Jogathon 2026 | SK Sri Petaling';
    $metaDescription = 'Kad kutipan digital Larian Sihat Jogathon SK Sri Petaling 2026 untuk sasaran kutipan RM150,000.';
    $directoryCount = $classDirectory->flatten(1)->count();
@endphp

@extends('layouts.jogathon-public')

@section('content')
    <div id="campaign-directory-shell" class="overflow-hidden bg-[#f4f8f5]">
        <div id="campaign-directory-slider" class="flex w-[200%] min-w-0 transition-transform duration-500 ease-out motion-reduce:transition-none">
            <section id="kempen" class="w-1/2 min-w-0 shrink-0">
                <div class="overflow-hidden bg-gradient-to-br from-emerald-950 via-emerald-800 to-teal-700 text-white">
                    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1.08fr_.92fr] lg:items-center lg:py-16">
                        <div>
                            <p class="mb-3 inline-flex rounded-full bg-lime-300 px-3 py-1 text-xs font-extrabold uppercase tracking-[.16em] text-emerald-950">Kad kutipan digital • Jogathon SKSP 2026</p>
                            <h1 class="max-w-3xl text-4xl font-black leading-tight sm:text-5xl">Larian Sihat Jogathon 2026</h1>
                            <p class="mt-3 text-xl font-extrabold text-lime-100 sm:text-2xl">Bersama Melangkah, Bersama Membina</p>
                            <p class="mt-4 max-w-2xl text-base leading-7 text-emerald-50 sm:text-lg">{{ $campaign->description ?: 'Sumbangan digital untuk membantu penyelenggaraan, menaik taraf prasarana sekolah dan menambah tabung kebajikan pelajar SK Sri Petaling.' }}</p>
                            <dl class="mt-6 grid max-w-2xl gap-3 text-sm sm:grid-cols-3">
                                <div class="rounded-2xl bg-white/12 p-4 ring-1 ring-white/20"><dt class="text-xs font-bold uppercase tracking-[.12em] text-lime-100">Tarikh</dt><dd class="mt-1 font-black">24 Okt 2026</dd></div>
                                <div class="rounded-2xl bg-white/12 p-4 ring-1 ring-white/20"><dt class="text-xs font-bold uppercase tracking-[.12em] text-lime-100">Masa</dt><dd class="mt-1 font-black">7.00 pagi</dd></div>
                                <div class="rounded-2xl bg-white/12 p-4 ring-1 ring-white/20"><dt class="text-xs font-bold uppercase tracking-[.12em] text-lime-100">Tempat</dt><dd class="mt-1 font-black">Padang SKSP</dd></div>
                            </dl>
                            <div class="mt-5 flex flex-wrap gap-3 text-sm font-bold">
                                <span class="rounded-full bg-white/12 px-4 py-2 ring-1 ring-white/20">Sasaran sekolah: {{ JogathonAmount::ringgit($summary['target_amount_sen']) }}</span>
                                <span class="rounded-full bg-white/12 px-4 py-2 ring-1 ring-white/20">Kutipan: 5 Ogos - 24 Oktober 2026</span>
                                <span class="rounded-full bg-white/12 px-4 py-2 ring-1 ring-white/20">Minima: RM50 seorang</span>
                                <span class="rounded-full bg-white/12 px-4 py-2 ring-1 ring-white/20">RM1 = 10 meter</span>
                            </div>
                            <button type="button" data-show-directory class="mt-8 inline-flex w-full items-center justify-center rounded-full bg-white px-6 py-3 text-sm font-black text-emerald-900 shadow-lg shadow-emerald-950/20 transition hover:-translate-y-0.5 hover:bg-lime-100 sm:w-auto">
                                Direktori Peserta →
                            </button>
                        </div>

                        <div class="rounded-2xl bg-white p-6 text-slate-900 shadow-2xl shadow-emerald-950/30">
                            <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Kad kutipan digital</p>
                            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <p class="text-4xl font-black text-emerald-950">{{ JogathonAmount::ringgit($summary['target_amount_sen']) }}</p>
                                    <p class="text-sm text-slate-500">sasaran kutipan PIBG SK Sri Petaling</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-bold text-slate-500">Terkumpul</p>
                                    <p class="text-2xl font-black text-emerald-800">{{ JogathonAmount::ringgit($summary['amount_sen']) }}</p>
                                </div>
                            </div>
                            <div class="mt-5 h-3 overflow-hidden rounded-full bg-emerald-100" role="progressbar" aria-valuenow="{{ $summary['progress_percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-gradient-to-r from-lime-400 to-emerald-600" style="width: {{ $summary['visual_percent'] }}%"></div>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                                <span>{{ number_format($summary['progress_percent'], 1) }}% sasaran sekolah</span>
                                <span>Baki {{ JogathonAmount::ringgit($summary['remaining_amount_sen']) }}</span>
                            </div>
                            <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-xl bg-emerald-50 p-3"><dt class="text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Jarak maya</dt><dd class="mt-1 font-black text-emerald-950">{{ JogathonAmount::kilometres($summary['distance_cm']) }}</dd></div>
                                <div class="rounded-xl bg-lime-50 p-3"><dt class="text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Peserta awam</dt><dd class="mt-1 font-black text-emerald-950">{{ number_format($summary['participant_count']) }}</dd></div>
                            </dl>
                            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                                <p class="font-black">Sumbangan anda amat dihargai. Terima kasih.</p>
                                <p class="mt-1 text-xs leading-5">Versi digital ini menggantikan rujukan kad kutipan fizikal untuk paparan sasaran, bucket dana dan pencapaian peserta.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="border-b border-emerald-950/10 bg-white">
                    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                        <div class="grid gap-8 lg:grid-cols-[.95fr_1.05fr]">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Bucket kempen</p>
                                <h2 class="mt-1 text-2xl font-black text-emerald-950">Bagaimana sekolah merancang penggunaan {{ JogathonAmount::ringgit($summary['target_amount_sen']) }}</h2>
                                <p class="mt-3 text-sm leading-6 text-slate-600">Setiap penyumbang memilih bucket ketika menyumbang. Paparan ini menunjukkan sasaran, kutipan semasa dan perjalanan setiap bucket secara agregat.</p>
                            </div>
                            <div class="space-y-3">
                                @foreach ($summary['cause_totals'] as $cause)
                                    @php
                                        $share = $summary['target_amount_sen'] > 0
                                            ? round(($cause['target_amount_sen'] / $summary['target_amount_sen']) * 100, 1)
                                            : 0;
                                    @endphp
                                    <article class="rounded-2xl bg-[#f4f8f5] p-4 ring-1 ring-emerald-950/10">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h3 class="font-bold leading-6 text-slate-900">{{ $cause['name'] }}</h3>
                                                <p class="mt-1 text-xs text-slate-500">{{ JogathonAmount::ringgit($cause['amount_sen']) }} daripada {{ JogathonAmount::ringgit($cause['target_amount_sen']) }} • {{ number_format($share, 1) }}% pelan</p>
                                            </div>
                                            <span class="shrink-0 text-sm font-extrabold text-emerald-800">{{ number_format($cause['progress_percent'], 1) }}%</span>
                                        </div>
                                        <div class="mt-4 grid grid-cols-[4.5rem_1fr] items-center gap-3">
                                            <span class="text-xs font-bold text-slate-500">Perjalanan</span>
                                            <div class="h-2 overflow-hidden rounded-full bg-emerald-100">
                                                <div class="h-full rounded-full bg-gradient-to-r from-lime-400 to-emerald-600" style="width: {{ $cause['visual_percent'] }}%"></div>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-[#f7faf8]">
                    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 sm:px-6 lg:grid-cols-[.9fr_1.1fr]">
                        <article class="rounded-2xl bg-emerald-950 p-6 text-white shadow-sm">
                            <p class="text-xs font-bold uppercase tracking-[.16em] text-lime-200">Hadiah Motivasi Khas</p>
                            <h2 class="mt-2 text-2xl font-black">Top Achiever Jogathon</h2>
                            <p class="mt-3 text-sm leading-6 text-emerald-50">Peserta dengan jumlah kutipan sah tertinggi akan diketengahkan sebagai penerima hadiah motivasi khas. Kutipan tertangguh atau gagal tidak dikira dalam kedudukan.</p>
                            @if ($leaderboard->first() && (int) ($leaderboard->first()->collected_amount_sen ?? 0) > 0)
                                <div class="mt-6 rounded-xl bg-white/10 p-4 ring-1 ring-white/15">
                                    <p class="text-xs font-bold uppercase tracking-[.12em] text-lime-200">Pendahulu semasa</p>
                                    <p class="mt-1 text-xl font-black">{{ $leaderboard->first()->public_display_name }}</p>
                                    <p class="text-sm text-emerald-50">{{ JogathonAmount::ringgit((int) $leaderboard->first()->collected_amount_sen) }} • {{ JogathonAmount::metres(JogathonAmount::distanceCmFromSen((int) $leaderboard->first()->collected_amount_sen)) }}</p>
                                </div>
                            @endif
                        </article>

                        <div>
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Leaderboard</p>
                                    <h2 class="mt-1 text-2xl font-black text-emerald-950">Pencapaian peserta</h2>
                                </div>
                                <button type="button" data-show-directory class="w-full rounded-full border border-emerald-700/20 px-4 py-2 text-xs font-black text-emerald-800 hover:bg-emerald-50 sm:w-auto">Lihat direktori penuh</button>
                            </div>
                            <div class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-emerald-950/10">
                                @forelse ($leaderboard as $participant)
                                    @php
                                        $rankAmount = max(0, (int) ($participant->collected_amount_sen ?? 0));
                                        $rankPercent = round(($rankAmount / max(1, $participant->target_amount_sen)) * 100, 1);
                                    @endphp
                                    <a href="{{ route('jogathon.public.participants.show', [$campaign, $participant->publicUrlIdentifier()]) }}" class="grid grid-cols-[3rem_1fr_auto] items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-emerald-50">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-sm font-black text-emerald-900">{{ $loop->iteration }}</span>
                                        <span>
                                            <span class="block font-bold text-slate-900">{{ $participant->public_display_name }}</span>
                                            @if (filled($participant->class_name_snapshot))
                                                <span class="block text-xs text-slate-500">Kelas {{ $participant->class_name_snapshot }}</span>
                                            @endif
                                        </span>
                                        <span class="text-right text-sm">
                                            <span class="block font-black text-emerald-800">{{ JogathonAmount::ringgit($rankAmount) }}</span>
                                            <span class="text-xs text-slate-500">{{ number_format($rankPercent, 1) }}%</span>
                                        </span>
                                    </a>
                                @empty
                                    <div class="p-6 text-sm text-slate-600">Leaderboard akan dipaparkan selepas sumbangan sah pertama direkodkan.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>
            </section>

            <section id="direktori" class="min-h-[calc(100vh-4.5rem)] w-1/2 min-w-0 shrink-0 bg-[#f4f8f5]">
                <div class="sticky top-0 z-20 border-b border-emerald-950/10 bg-white/95 backdrop-blur">
                    <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <button type="button" data-show-campaign class="rounded-full border border-emerald-700/20 px-4 py-2 text-xs font-black text-emerald-800 hover:bg-emerald-50">← Kempen</button>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Direktori peserta</p>
                                    <h2 class="text-xl font-black text-emerald-950">Senarai awam mengikut kelas</h2>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('jogathon.public.participants.search', $campaign) }}" class="grid w-full grid-cols-[1fr_auto] gap-2 lg:max-w-md" role="search">
                                @csrf
                                <label for="participant-search" class="sr-only">Nama murid atau nama paparan peserta</label>
                                <input id="participant-search" name="student_name" maxlength="120" placeholder="Cari nama murid" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                                <button class="rounded-xl bg-emerald-800 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-900">Buka</button>
                            </form>
                        </div>
                        <div class="mt-4 flex touch-pan-x gap-2 overflow-x-auto pb-2" aria-label="Tapis kelas">
                            <button type="button" data-class-filter="all" class="directory-class-pill whitespace-nowrap rounded-full bg-emerald-900 px-4 py-2 text-xs font-black text-white" aria-pressed="true">Semua kelas <span class="opacity-80">({{ $directoryCount }})</span></button>
                            @foreach ($classDirectory as $className => $classParticipants)
                                <button type="button" data-class-filter="{{ $className }}" class="directory-class-pill whitespace-nowrap rounded-full border border-emerald-700/20 bg-white px-4 py-2 text-xs font-black text-emerald-800 hover:bg-emerald-50" aria-pressed="false">{{ $className }} <span class="opacity-70">({{ $classParticipants->count() }})</span></button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                    <p class="max-w-3xl text-sm leading-6 text-slate-600">Nama penuh murid tidak dipaparkan. Direktori menggunakan nama paparan sahaja; nombor murid, kod keluarga dan maklumat penjaga kekal tertutup. Gunakan butang kelas di atas untuk menapis peserta.</p>
                    @error('student_name')
                        <p class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">{{ $message }}</p>
                    @enderror

                    @if ($classDirectory->isNotEmpty())
                        <div class="mt-6 space-y-6">
                            @foreach ($classDirectory as $className => $classParticipants)
                                <article data-directory-class="{{ $className }}" class="directory-class-section rounded-3xl bg-white p-5 shadow-sm ring-1 ring-emerald-950/10">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-[.14em] text-emerald-700">Kelas</p>
                                            <h3 class="text-2xl font-black text-emerald-950">{{ $className }}</h3>
                                        </div>
                                        <span class="rounded-full bg-lime-100 px-3 py-1 text-xs font-bold text-emerald-800">{{ $classParticipants->count() }} peserta</span>
                                    </div>
                                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ($classParticipants as $participant)
                                            @php
                                                $collected = max(0, (int) ($participant->collected_amount_sen ?? 0));
                                                $percent = round(($collected / max(1, $participant->target_amount_sen)) * 100, 1);
                                            @endphp
                                            <a href="{{ route('jogathon.public.participants.show', [$campaign, $participant->publicUrlIdentifier()]) }}" class="group rounded-2xl border border-emerald-950/10 bg-[#f8fbf9] p-4 transition hover:-translate-y-0.5 hover:border-emerald-500 hover:bg-emerald-50">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <h4 class="font-extrabold text-emerald-950 group-hover:text-emerald-700">{{ $participant->public_display_name }}</h4>
                                                        <p class="mt-1 text-xs text-slate-500">{{ JogathonAmount::ringgit($collected) }} • {{ JogathonAmount::metres(JogathonAmount::distanceCmFromSen($collected)) }}</p>
                                                    </div>
                                                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-emerald-800 ring-1 ring-emerald-950/10">{{ number_format($percent, 1) }}%</span>
                                                </div>
                                                <div class="mt-4 h-2 overflow-hidden rounded-full bg-emerald-100">
                                                    <div class="h-full rounded-full bg-emerald-600" style="width: {{ min(100, $percent) }}%"></div>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-6 rounded-2xl border border-dashed border-emerald-300 bg-white p-8 text-center text-sm text-slate-600">
                            Senarai peserta awam belum diterbitkan.
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const slider = document.getElementById('campaign-directory-slider');
            const shell = document.getElementById('campaign-directory-shell');
            const showDirectoryButtons = document.querySelectorAll('[data-show-directory]');
            const showCampaignButtons = document.querySelectorAll('[data-show-campaign]');
            const filterButtons = document.querySelectorAll('[data-class-filter]');
            const classSections = document.querySelectorAll('[data-directory-class]');

            const showDirectory = () => {
                slider.style.transform = 'translateX(-50%)';
                shell.dataset.activePanel = 'directory';
                history.replaceState(null, '', '#direktori');
                document.getElementById('direktori').scrollIntoView({ block: 'start' });
            };

            const showCampaign = () => {
                slider.style.transform = 'translateX(0)';
                shell.dataset.activePanel = 'campaign';
                history.replaceState(null, '', window.location.pathname + window.location.search);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            };

            showDirectoryButtons.forEach((button) => button.addEventListener('click', showDirectory));
            showCampaignButtons.forEach((button) => button.addEventListener('click', showCampaign));

            filterButtons.forEach((button) => button.addEventListener('click', () => {
                const selectedClass = button.dataset.classFilter;

                filterButtons.forEach((item) => {
                    const isActive = item === button;
                    item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    item.classList.toggle('bg-emerald-900', isActive);
                    item.classList.toggle('text-white', isActive);
                    item.classList.toggle('border', ! isActive);
                    item.classList.toggle('border-emerald-700/20', ! isActive);
                    item.classList.toggle('bg-white', ! isActive);
                    item.classList.toggle('text-emerald-800', ! isActive);
                });

                classSections.forEach((section) => {
                    section.classList.toggle('hidden', selectedClass !== 'all' && section.dataset.directoryClass !== selectedClass);
                });
            }));

            if (window.location.hash === '#direktori') {
                requestAnimationFrame(showDirectory);
            }
        });
    </script>
@endsection
