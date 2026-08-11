@php
    use App\Support\JogathonAmount;

    $title = 'Sumbang untuk '.$participant->public_display_name.' | Jogathon SKSP 2026';
    $metaDescription = 'Halaman sumbangan khas untuk '.$participant->public_display_name.' dalam Larian Sihat Jogathon SK Sri Petaling 2026.';
    $participantUrl = $participant->publicShortUrl() ?: route('jogathon.public.participants.show', [$campaign, $participant->publicUrlIdentifier()]);
@endphp

@extends('layouts.jogathon-public')

@section('content')
    <section class="bg-gradient-to-b from-emerald-950 to-emerald-800 text-white">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:items-end">
            <div>
                <a href="{{ $participantUrl }}" class="text-sm font-semibold text-emerald-100 hover:text-white">Kembali ke halaman peserta</a>
                <p class="mt-8 text-xs font-bold uppercase tracking-[.16em] text-lime-300">Halaman sumbangan peserta</p>
                <h1 class="mt-2 text-4xl font-black leading-tight sm:text-5xl">{{ $participant->public_display_name }}</h1>
                @if ($campaign->show_class_publicly && filled($participant->class_name_snapshot))
                    <p class="mt-2 text-sm font-semibold text-emerald-100">Kelas {{ $participant->class_name_snapshot }}</p>
                @endif
                <p class="mt-4 max-w-xl text-base leading-7 text-emerald-50">Sumbangan anda akan menggerakkan perjalanan maya peserta ini dan dimasukkan ke dalam bucket kempen sekolah yang dipilih.</p>
            </div>

            <div class="rounded-2xl bg-white p-5 text-slate-900 shadow-2xl shadow-emerald-950/30">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Kemajuan peserta</p>
                        <p class="mt-1 text-3xl font-black text-emerald-950">{{ JogathonAmount::ringgit($progress['amount_sen']) }}</p>
                    </div>
                    <p class="text-right text-xl font-black text-emerald-700">{{ number_format($progress['progress_percent'], 1) }}%</p>
                </div>
                <div class="mt-4 h-3 overflow-hidden rounded-full bg-emerald-100">
                    <div class="h-full rounded-full bg-gradient-to-r from-lime-400 to-emerald-600" style="width: {{ $progress['visual_percent'] }}%"></div>
                </div>
                <div class="mt-3 flex justify-between text-xs text-slate-500">
                    <span>{{ JogathonAmount::metres($progress['distance_cm']) }}</span>
                    <span>Sasaran {{ JogathonAmount::metres($progress['target_distance_cm']) }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-6xl gap-6 px-4 py-10 sm:px-6 lg:grid-cols-[1.25fr_.75fr]">
        <div class="rounded-2xl border border-emerald-950/10 bg-white p-5 shadow-sm sm:p-7">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Borang sumbangan</p>
            <h2 class="mt-1 text-2xl font-black text-emerald-950">Sumbang untuk peserta ini</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Maklumat telefon dan e-mel digunakan untuk ToyyibPay sahaja dan tidak dipaparkan pada halaman awam.</p>

            <form id="jogathon-donation-selector" method="POST" action="{{ route('jogathon.public.participants.donations.store', [$campaign, $participant->publicUrlIdentifier()]) }}" class="mt-6">
                @csrf
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                    @foreach ([1000 => ['RM10', '100 m'], 2000 => ['RM20', '200 m'], 3000 => ['RM30', '300 m'], 5000 => ['RM50', '500 m'], 10000 => ['RM100', '1 km']] as $sen => [$label, $distance])
                        <button type="button" data-amount-sen="{{ $sen }}" class="donation-preset rounded-xl border border-emerald-200 bg-emerald-50 px-2 py-3 text-center hover:border-emerald-600 hover:bg-emerald-100">
                            <span class="block text-sm font-black text-emerald-950">{{ $label }}</span>
                            <span class="mt-0.5 block text-[11px] text-emerald-700">{{ $distance }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="custom-amount" class="text-sm font-bold text-slate-700">Jumlah sumbangan (RM)</label>
                        <input id="custom-amount" name="amount" value="{{ old('amount', $selectedAmount) }}" inputmode="decimal" placeholder="Contoh: 25.00" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                        @error('amount')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="cause-selection" class="text-sm font-bold text-slate-700">Bucket kempen</label>
                        <select id="cause-selection" name="cause_id" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200" @if (! $campaign->allow_unspecified_cause) required @endif>
                            <option value="">Pilih bucket</option>
                            @foreach ($activeCauses as $cause)
                                <option value="{{ $cause->id }}" @selected((string) old('cause_id') === (string) $cause->id)>{{ $cause->name }}</option>
                            @endforeach
                            @if ($campaign->allow_unspecified_cause)
                                <option value="">Belum ditetapkan</option>
                            @endif
                        </select>
                        @error('cause_id')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="donor-name" class="text-sm font-bold text-slate-700">Nama penyumbang</label>
                        <input id="donor-name" name="donor_name" value="{{ old('donor_name') }}" required maxlength="120" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                        @error('donor_name')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="donor-email" class="text-sm font-bold text-slate-700">E-mel</label>
                        <input id="donor-email" name="donor_email" value="{{ old('donor_email') }}" type="email" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                        @error('donor_email')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="donor-phone" class="text-sm font-bold text-slate-700">Telefon</label>
                        <input id="donor-phone" name="donor_phone" value="{{ old('donor_phone') }}" required maxlength="25" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                        @error('donor_phone')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <label for="encouragement-message" class="text-sm font-bold text-slate-700">Mesej sokongan (pilihan)</label>
                    <textarea id="encouragement-message" name="encouragement_message" maxlength="280" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200">{{ old('encouragement_message') }}</textarea>
                    @error('encouragement_message')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>

                <label class="mt-4 flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700">
                    <input type="checkbox" name="is_anonymous_public" value="1" @checked(old('is_anonymous_public')) class="mt-1 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                    <span>Paparkan sebagai Tanpa Nama pada halaman awam.</span>
                </label>

                @error('payment_gateway')
                    <p class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $message }}</p>
                @enderror

                <div class="mt-5 rounded-2xl bg-emerald-950 p-4 text-white">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs text-emerald-200">Pilihan anda</p>
                            <p id="donation-preview" class="mt-1 text-lg font-black">Pilih jumlah</p>
                        </div>
                        <span class="rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold">RM1 = 10 m</span>
                    </div>
                </div>

                <button type="submit" class="mt-4 w-full rounded-xl bg-emerald-800 px-5 py-3.5 text-sm font-extrabold text-white hover:bg-emerald-900">Teruskan ke ToyyibPay</button>
                <p class="mt-3 text-xs leading-5 text-slate-500">Transaksi Jogathon menggunakan kategori dan secret ToyyibPay berasingan daripada Sumbangan PIBG keluarga.</p>
            </form>
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl bg-emerald-950 p-5 text-white">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-lime-200">Impak sumbangan</p>
                <p class="mt-2 text-sm leading-6 text-emerald-50">Setiap RM1 menambah 10 meter perjalanan maya peserta. Sumbangan sah akan muncul dalam progress selepas pengesahan ToyyibPay.</p>
            </div>
            <div class="rounded-2xl bg-white p-5 ring-1 ring-emerald-950/10">
                <h2 class="font-black text-emerald-950">Bucket tersedia</h2>
                <div class="mt-3 space-y-2">
                    @foreach ($activeCauses as $cause)
                        <div class="rounded-xl bg-[#f4f8f5] p-3">
                            <p class="text-sm font-bold text-slate-900">{{ $cause->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">Sasaran {{ JogathonAmount::ringgit((int) $cause->target_amount_sen) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const preview = document.getElementById('donation-preview');
            const customAmount = document.getElementById('custom-amount');
            const presets = document.querySelectorAll('.donation-preset');

            const showAmount = (sen) => {
                const safeSen = Math.max(0, Number(sen) || 0);
                if (safeSen === 0) { preview.textContent = 'Pilih jumlah'; return; }
                const ringgit = safeSen / 100;
                const metres = safeSen / 10;
                preview.textContent = `RM${ringgit.toFixed(2)} = ${metres.toLocaleString('ms-MY')} m`;
            };

            presets.forEach((button) => button.addEventListener('click', () => {
                presets.forEach((item) => item.classList.remove('ring-2', 'ring-emerald-600'));
                button.classList.add('ring-2', 'ring-emerald-600');
                customAmount.value = (Number(button.dataset.amountSen) / 100).toFixed(2);
                showAmount(button.dataset.amountSen);
            }));

            customAmount.addEventListener('input', () => {
                presets.forEach((item) => item.classList.remove('ring-2', 'ring-emerald-600'));
                const value = customAmount.value.trim();
                showAmount(/^\d+(?:\.\d{0,2})?$/.test(value) ? Math.round(Number(value) * 100) : 0);
            });

            showAmount(Math.round(Number(customAmount.value || 0) * 100));
        });
    </script>
@endsection
