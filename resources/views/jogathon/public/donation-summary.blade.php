@php
    use App\Models\JogathonContribution;
    use App\Support\JogathonAmount;

    $title = 'Ringkasan Sumbangan Jogathon | SK Sri Petaling';
    $metaDescription = 'Ringkasan transaksi sumbangan Jogathon Digital SK Sri Petaling.';
    $participant = $contribution->participant;
    $isSuccessful = $contribution->status === JogathonContribution::STATUS_SUCCESSFUL;
@endphp

@extends('layouts.jogathon-public')

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-emerald-950/10 sm:p-8">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Ringkasan sumbangan</p>
            <h1 class="mt-2 text-3xl font-black text-emerald-950">{{ $isSuccessful ? 'Sumbangan berjaya disahkan' : 'Sumbangan sedang diproses' }}</h1>

            <div class="mt-6 rounded-2xl {{ $isSuccessful ? 'bg-emerald-50 text-emerald-950' : 'bg-amber-50 text-amber-950' }} p-5">
                <p class="text-sm font-semibold">
                    @if ($isSuccessful)
                        Terima kasih. ToyyibPay telah mengesahkan sumbangan ini dan jarak maya peserta telah dikemas kini.
                    @else
                        Jika pembayaran telah dibuat, sistem akan mengemas kini status selepas pengesahan callback ToyyibPay diterima.
                    @endif
                </p>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4">
                    <dt class="text-xs font-bold uppercase tracking-[.12em] text-slate-500">Peserta</dt>
                    <dd class="mt-1 font-black text-slate-900">{{ $participant?->public_display_name }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 p-4">
                    <dt class="text-xs font-bold uppercase tracking-[.12em] text-slate-500">Jumlah</dt>
                    <dd class="mt-1 font-black text-slate-900">{{ JogathonAmount::ringgit((int) $contribution->amount_sen) }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 p-4">
                    <dt class="text-xs font-bold uppercase tracking-[.12em] text-slate-500">Jarak maya</dt>
                    <dd class="mt-1 font-black text-slate-900">{{ JogathonAmount::metres((int) $contribution->distance_cm) }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 p-4">
                    <dt class="text-xs font-bold uppercase tracking-[.12em] text-slate-500">Status</dt>
                    <dd class="mt-1 font-black text-slate-900">{{ ucfirst(str_replace('_', ' ', (string) $contribution->status)) }}</dd>
                </div>
            </dl>

            <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                <p><span class="font-bold text-slate-800">Rujukan:</span> {{ $contribution->external_order_id }}</p>
                @if (filled($contribution->provider_reference))
                    <p class="mt-1"><span class="font-bold text-slate-800">Rujukan ToyyibPay:</span> {{ $contribution->provider_reference }}</p>
                @endif
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                @if ($participant)
                    <a href="{{ route('jogathon.public.participants.show', [$campaign, $participant->publicUrlIdentifier()]) }}" class="rounded-xl bg-emerald-800 px-5 py-3 text-center text-sm font-extrabold text-white hover:bg-emerald-900">Kembali ke peserta</a>
                @endif
                <a href="{{ route('home') }}" class="rounded-xl border border-emerald-700/20 px-5 py-3 text-center text-sm font-bold text-emerald-800 hover:bg-emerald-50">Laman kempen</a>
            </div>
        </div>
    </section>
@endsection
