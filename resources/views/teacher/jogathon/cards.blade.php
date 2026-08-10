<x-layouts::app :title="__('Kad Peserta Jogathon')">
    <div class="space-y-6">
        <header class="overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-emerald-800 to-teal-600 p-6 text-white shadow-lg">
            <p class="text-xs font-bold uppercase tracking-[0.24em] text-lime-200">Jogathon Digital</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight">Daftar Nombor Kad Peserta</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-50">
                Daftar nombor kad fizikal seperti <span class="font-mono font-bold">ssp-0001</span>. Nombor ini menjadi pautan pendek peserta dan boleh digunakan pada QR kad.
            </p>
        </header>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-bold">Sila semak maklumat berikut:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            @if (! $campaign)
                <p class="text-sm text-zinc-500">Belum ada kempen Jogathon aktif untuk pendaftaran kad.</p>
            @else
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-zinc-900">{{ $campaign->name }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">
                            {{ $isClassTeacherOnly ? 'Paparan dihadkan kepada kelas anda.' : 'Paparan semua kelas untuk penyelaras.' }}
                        </p>
                    </div>
                    @if ($campaign->isPubliclyAvailable())
                        <a href="{{ route('jogathon.public.campaigns.show', $campaign) }}" target="_blank" rel="noopener" class="rounded-xl border border-emerald-700 px-4 py-2.5 text-sm font-bold text-emerald-800 hover:bg-emerald-50">Laman awam</a>
                    @endif
                </div>

                @if ($participants?->isNotEmpty())
                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm">
                            <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                                <tr>
                                    <th class="px-4 py-3">Murid / Alias</th>
                                    <th class="px-4 py-3">Kelas</th>
                                    <th class="px-4 py-3">Nombor Kad</th>
                                    <th class="px-4 py-3">Pautan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @foreach ($participants as $participant)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-zinc-900">{{ $participant->student?->full_name ?: $participant->public_display_name }}</p>
                                            <p class="mt-1 text-xs text-zinc-500">Paparan awam: {{ $participant->public_display_name }}</p>
                                        </td>
                                        <td class="px-4 py-3">{{ $participant->class_name_snapshot ?: '—' }}</td>
                                        <td class="px-4 py-3">
                                            <form method="POST" action="{{ route('system.jogathon.participants.physical-card-number.update', $participant) }}" class="flex min-w-[16rem] gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <input name="physical_card_number" value="{{ old('physical_card_number', $participant->physical_card_number) }}" placeholder="ssp-0001" pattern="ssp-[0-9]{4,8}" class="w-32 rounded-lg border-zinc-300 font-mono text-xs lowercase">
                                                <button class="rounded-lg bg-emerald-800 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-900">Simpan</button>
                                            </form>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs">
                                            @if ($participant->physical_card_number && $participant->isPubliclyVisible())
                                                <a href="{{ $participant->publicShortUrl() }}" target="_blank" rel="noopener" class="text-sky-700 underline">{{ parse_url($participant->publicShortUrl(), PHP_URL_PATH) }}</a>
                                            @else
                                                <span class="text-zinc-500">Daftar nombor dahulu</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $participants->links() }}</div>
                @else
                    <p class="mt-5 rounded-2xl border border-dashed border-zinc-300 p-6 text-sm text-zinc-500">Tiada peserta layak dalam skop anda.</p>
                @endif
            @endif
        </section>
    </div>
</x-layouts::app>
