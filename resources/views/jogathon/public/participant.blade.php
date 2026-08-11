@php
    use App\Support\JogathonAmount;

    $title = $participant->public_display_name.' | '.$campaign->name;
    $metaDescription = 'Sokong perjalanan maya '.$participant->public_display_name.' untuk Jogathon Digital SK Sri Petaling.';
    $participantUrl = $participant->publicShortUrl() ?: route('jogathon.public.participants.show', [$campaign, $participant->publicUrlIdentifier()]);
    $whatsAppUrl = $participantUrl.(str_contains($participantUrl, '?') ? '&' : '?').'src=whatsapp';
    $copyUrl = $participantUrl.(str_contains($participantUrl, '?') ? '&' : '?').'src=copy';
    $whatsAppText = rawurlencode('Jom sokong perjalanan Jogathon Digital '.$participant->public_display_name.' di '.$whatsAppUrl);
    $supportDisplayName = $participant->student?->full_name ?: $participant->public_display_name;
@endphp

@extends('layouts.jogathon-public')

@section('content')
    <section class="jogathon-grid bg-gradient-to-b from-emerald-950 to-emerald-800 text-white">
        <div class="mx-auto max-w-6xl px-4 pb-28 pt-10 sm:px-6 sm:pt-14">
            <a href="{{ route('jogathon.public.campaigns.show', $campaign) }}" class="text-sm font-semibold text-emerald-100 hover:text-white">← Kembali ke kempen</a>
            <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.16em] text-lime-300">Perjalanan peserta</p>
                    <h1 class="mt-2 text-4xl font-black leading-tight sm:text-5xl">{{ $participant->public_display_name }}</h1>
                    @if ($campaign->show_class_publicly && filled($participant->class_name_snapshot))
                        <p class="mt-2 text-sm text-emerald-100">Kelas {{ $participant->class_name_snapshot }}</p>
                    @endif
                </div>
                @if ($progress['has_reached_target'])
                    <span class="w-fit rounded-full bg-lime-300 px-4 py-2 text-sm font-black text-emerald-950">🏁 Sasaran dicapai!</span>
                @endif
            </div>
        </div>
    </section>

    <section class="mx-auto -mt-20 max-w-6xl px-4 sm:px-6">
        <div class="rounded-3xl bg-white p-5 shadow-2xl shadow-emerald-950/15 sm:p-8">
            <div class="grid min-w-0 gap-6 lg:grid-cols-[1.4fr_.6fr] lg:items-center">
                <div>
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-500">Jarak terkumpul</p>
                            <p class="mt-1 text-4xl font-black text-emerald-950 sm:text-5xl">{{ JogathonAmount::kilometres($progress['distance_cm']) }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ JogathonAmount::metres($progress['distance_cm']) }} daripada {{ JogathonAmount::metres($progress['target_distance_cm']) }}</p>
                        </div>
                        <p class="text-right text-2xl font-black text-emerald-700">{{ number_format($progress['progress_percent'], 1) }}%</p>
                    </div>

                    <div class="journey-track relative mt-8 h-14 overflow-visible rounded-full bg-emerald-100 before:absolute before:inset-2 before:rounded-full sm:h-16">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-gradient-to-r from-lime-400 via-emerald-500 to-teal-600" style="width: {{ $progress['visual_percent'] }}%"></div>
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-lg" aria-label="Mula">●</span>
                        <span class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-lg" aria-label="Separuh jalan">◆</span>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xl" aria-label="Penamat 5 kilometer">🏁</span>
                        <span class="journey-runner absolute top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 text-3xl" style="left: clamp(22px, {{ $progress['visual_percent'] }}%, calc(100% - 24px))" aria-hidden="true">🏃</span>
                    </div>
                    <div class="mt-2 flex justify-between text-[11px] font-bold uppercase tracking-wide text-slate-400"><span>Mula</span><span>2.5 km</span><span>5 km</span></div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-2xl bg-emerald-950 p-4 text-white">
                        <p class="text-xs text-emerald-200">Jumlah terkumpul</p>
                        <p class="mt-1 text-2xl font-black">{{ JogathonAmount::ringgit($progress['amount_sen']) }}</p>
                    </div>
                    <div class="rounded-2xl bg-lime-100 p-4 text-emerald-950">
                        <p class="text-xs text-emerald-800">Baki sasaran</p>
                        <p class="mt-1 text-2xl font-black">{{ JogathonAmount::ringgit($progress['remaining_amount_sen']) }}</p>
                    </div>
                </div>
            </div>

            @if ($progress['progress_percent'] > 100)
                <p class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">Hebat! Perjalanan kini {{ number_format($progress['progress_percent'], 1) }}% dan terus dikira melebihi sasaran.</p>
            @endif
        </div>
    </section>

    <section class="mx-auto grid max-w-6xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1.1fr_.9fr]">
        <div class="rounded-3xl border border-emerald-950/10 bg-white p-5 shadow-sm sm:p-7">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Sumbangan peserta</p>
            <h2 class="mt-1 text-2xl font-black text-emerald-950">Sokong perjalanan {{ $supportDisplayName }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Halaman sumbangan khas disediakan untuk peserta ini. Penyumbang boleh memilih amaun, bucket kempen dan mesej sokongan sebelum diteruskan ke ToyyibPay.</p>

            <div class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-5">
                @foreach (['10.00' => ['RM10', '100 m'], '20.00' => ['RM20', '200 m'], '30.00' => ['RM30', '300 m'], '50.00' => ['RM50', '500 m'], '100.00' => ['RM100', '1 km']] as $amount => [$label, $distance])
                    <a href="{{ route('jogathon.public.participants.donations.create', [$campaign, $participant->publicUrlIdentifier(), 'amount' => $amount]) }}" class="rounded-xl border border-emerald-200 bg-emerald-50 px-2 py-3 text-center hover:border-emerald-600 hover:bg-emerald-100">
                        <span class="block text-sm font-black text-emerald-950">{{ $label }}</span>
                        <span class="mt-0.5 block text-[11px] text-emerald-700">{{ $distance }}</span>
                    </a>
                @endforeach
            </div>

            <a href="{{ route('jogathon.public.participants.donations.create', [$campaign, $participant->publicUrlIdentifier()]) }}" class="mt-5 block rounded-xl bg-emerald-800 px-5 py-3.5 text-center text-sm font-extrabold text-white hover:bg-emerald-900">Sumbang untuk peserta ini</a>
            <p class="mt-3 text-xs leading-5 text-slate-500">Transaksi Jogathon menggunakan konfigurasi ToyyibPay berasingan daripada Sumbangan PIBG keluarga.</p>
        </div>

        <aside class="space-y-6">
            <div class="rounded-3xl border border-emerald-950/10 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="text-lg font-black text-emerald-950">Kongsi perjalanan</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-[7rem_1fr] sm:items-center">
                    <img src="{{ route('jogathon.public.participants.qr', [$campaign, $participant->publicUrlIdentifier()]) }}" alt="Kod QR untuk halaman {{ $participant->public_display_name }}" class="size-28 rounded-xl border border-slate-200 bg-white p-1">
                    <div class="flex-1 space-y-2">
                        <a href="https://wa.me/?text={{ $whatsAppText }}" target="_blank" rel="noopener noreferrer" class="block rounded-xl bg-[#1f9d62] px-4 py-3 text-center text-sm font-extrabold text-white hover:bg-[#188651]">Kongsi di WhatsApp</a>
                        <button type="button" id="copy-jogathon-link" data-url="{{ $copyUrl }}" class="w-full rounded-xl border border-emerald-700/20 px-4 py-3 text-sm font-bold text-emerald-800 hover:bg-emerald-50">Salin pautan</button>
                    </div>
                </div>
                <p id="copy-feedback" class="mt-3 min-h-5 text-xs text-emerald-700" aria-live="polite"></p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white p-4 ring-1 ring-emerald-950/10"><p class="text-xs text-slate-500">Dalam talian</p><p class="mt-1 text-lg font-black text-emerald-950">{{ JogathonAmount::ringgit($progress['online_amount_sen']) }}</p></div>
                <div class="rounded-2xl bg-white p-4 ring-1 ring-emerald-950/10"><p class="text-xs text-slate-500">Kad fizikal</p><p class="mt-1 text-lg font-black text-emerald-950">{{ JogathonAmount::ringgit($progress['physical_amount_sen']) }}</p></div>
            </div>
        </aside>
    </section>

    <section class="border-y border-emerald-950/10 bg-white">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-2">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Agihan tujuan</p>
                <h2 class="mt-1 text-2xl font-black text-emerald-950">Sumbangan mengikut tujuan</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($progress['cause_totals'] as $causeTotal)
                        <div class="rounded-xl bg-[#f4f8f5] p-4"><p class="text-sm font-bold text-slate-800">{{ $causeTotal->name }}</p><p class="mt-1 text-xs text-slate-500">{{ JogathonAmount::ringgit((int) $causeTotal->amount_sen) }}</p></div>
                    @empty
                        <p class="rounded-xl bg-[#f4f8f5] p-4 text-sm text-slate-500">Belum ada sumbangan yang disahkan.</p>
                    @endforelse
                </div>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Aktiviti terkini</p>
                <h2 class="mt-1 text-2xl font-black text-emerald-950">Penyokong perjalanan</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($progress['recent_donors'] as $donor)
                        <article class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-4"><div><p class="text-sm font-bold text-slate-900">{{ $donor['display_name'] }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $donor['cause_name'] ?: 'Tujuan tidak dipaparkan' }}</p></div><p class="text-sm font-black text-emerald-800">{{ JogathonAmount::ringgit((int) $donor['amount_sen']) }}</p></div>
                            @if (filled($donor['message']))<p class="mt-3 border-l-2 border-lime-400 pl-3 text-sm italic text-slate-600">“{{ $donor['message'] }}”</p>@endif
                        </article>
                    @empty
                        <p class="rounded-xl bg-[#f4f8f5] p-4 text-sm text-slate-500">Aktiviti penyumbang akan dipaparkan selepas sumbangan disahkan.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('copy-jogathon-link').addEventListener('click', async (event) => {
                const feedback = document.getElementById('copy-feedback');
                try {
                    await navigator.clipboard.writeText(event.currentTarget.dataset.url);
                    feedback.textContent = 'Pautan telah disalin.';
                } catch {
                    feedback.textContent = 'Salin pautan melalui bar alamat pelayar.';
                }
            });
        });
    </script>
@endsection
