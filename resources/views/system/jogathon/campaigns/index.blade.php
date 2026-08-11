<x-layouts::app :title="__('Jogathon Digital')">
    <div class="space-y-6">
        <header class="overflow-hidden rounded-3xl bg-gradient-to-br from-sky-950 via-blue-800 to-emerald-600 p-6 text-white shadow-lg">
            <p class="text-xs font-bold uppercase tracking-[0.28em] text-sky-200">System Admin · Phase 2</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight">Jogathon Digital</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-sky-50/90">
                Urus kempen, tujuan, sasaran dan penyertaan murid untuk mini app Jogathon Digital.
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

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-zinc-900">Kempen</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @forelse ($campaigns as $campaign)
                        <a href="{{ route('system.jogathon.campaigns.index', ['campaign' => $campaign->id]) }}" class="rounded-2xl border p-4 transition {{ $selectedCampaign?->id === $campaign->id ? 'border-sky-400 bg-sky-50' : 'border-zinc-200 hover:bg-zinc-50' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div><p class="font-bold text-zinc-900">{{ $campaign->name }}</p><p class="mt-1 text-xs text-zinc-500">{{ $campaign->slug }}</p></div>
                                <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-bold text-zinc-700">{{ $statusOptions[$campaign->status] ?? $campaign->status }}</span>
                            </div>
                            <p class="mt-3 text-sm text-zinc-600">{{ number_format($campaign->eligible_participants_count) }} peserta layak · {{ $campaign->causes_count }} tujuan</p>
                        </a>
                    @empty
                        <p class="text-sm text-zinc-500">Belum ada kempen Jogathon.</p>
                    @endforelse
                </div>
            </div>

            <form method="POST" action="{{ route('system.jogathon.campaigns.store') }}" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                @csrf
                <h2 class="text-lg font-bold text-zinc-900">Kempen Baharu</h2>
                <div class="mt-4 grid gap-3">
                    <label class="text-sm font-semibold text-zinc-700">Nama<input name="name" required value="{{ old('name', 'Jogathon Digital SK Sri Petaling 2026') }}" class="mt-1 w-full rounded-xl border-zinc-300"></label>
                    <label class="text-sm font-semibold text-zinc-700">Penerangan<textarea name="description" rows="3" class="mt-1 w-full rounded-xl border-zinc-300">{{ old('description') }}</textarea></label>
                    <label class="text-sm font-semibold text-zinc-700">Status<select name="status" class="mt-1 w-full rounded-xl border-zinc-300">@foreach ($statusOptions as $value => $label)<option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="text-sm font-semibold text-zinc-700">Sasaran setiap murid (RM)<input type="number" name="default_target_amount_rm" min="1" step="0.01" value="{{ old('default_target_amount_rm', '500.00') }}" class="mt-1 w-full rounded-xl border-zinc-300"></label>
                    <input type="hidden" name="show_class_publicly" value="0"><input type="hidden" name="allow_public_indexing" value="0"><input type="hidden" name="allow_unspecified_cause" value="0">
                    <button class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-800">Cipta Draf + 5 Tujuan</button>
                </div>
            </form>
        </section>

        @if ($selectedCampaign)
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="text-xl font-black text-zinc-900">{{ $selectedCampaign->name }}</h2><p class="mt-1 text-sm text-zinc-500">RM1 = 10 meter · sasaran RM {{ number_format($selectedCampaign->default_target_amount_sen / 100, 2) }} / {{ number_format($selectedCampaign->default_target_distance_cm / 100) }} meter</p></div>
                    <div class="flex flex-wrap gap-2">
                        @if ($selectedCampaign->isPubliclyAvailable())
                            <a href="{{ route('jogathon.public.campaigns.show', $selectedCampaign) }}" target="_blank" rel="noopener" class="rounded-xl border border-emerald-700 px-4 py-2.5 text-sm font-bold text-emerald-800 hover:bg-emerald-50">Lihat Laman Awam</a>
                        @endif
                        <form method="POST" action="{{ route('system.jogathon.campaigns.provision', $selectedCampaign) }}">@csrf<button class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Segarkan Peserta Aktif</button></form>
                    </div>
                </div>

                <form method="POST" action="{{ route('system.jogathon.campaigns.update', $selectedCampaign) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @csrf @method('PATCH')
                    <label class="text-sm font-semibold text-zinc-700 xl:col-span-2">Nama<input name="name" required value="{{ old('name', $selectedCampaign->name) }}" class="mt-1 w-full rounded-xl border-zinc-300"></label>
                    <label class="text-sm font-semibold text-zinc-700">Status<select name="status" class="mt-1 w-full rounded-xl border-zinc-300">@foreach ($statusOptions as $value => $label)<option value="{{ $value }}" @selected(old('status', $selectedCampaign->status) === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="text-sm font-semibold text-zinc-700">Sasaran (RM)<input type="number" name="default_target_amount_rm" min="1" step="0.01" value="{{ old('default_target_amount_rm', number_format($selectedCampaign->default_target_amount_sen / 100, 2, '.', '')) }}" class="mt-1 w-full rounded-xl border-zinc-300"></label>
                    <label class="text-sm font-semibold text-zinc-700">Mula<input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($selectedCampaign->starts_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border-zinc-300"></label>
                    <label class="text-sm font-semibold text-zinc-700">Tamat<input type="datetime-local" name="ends_at" value="{{ old('ends_at', optional($selectedCampaign->ends_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border-zinc-300"></label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-zinc-700"><input type="checkbox" name="show_class_publicly" value="1" @checked(old('show_class_publicly', $selectedCampaign->show_class_publicly))> Papar kelas secara awam</label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-zinc-700"><input type="checkbox" name="allow_public_indexing" value="1" @checked(old('allow_public_indexing', $selectedCampaign->allow_public_indexing))> Benarkan pengindeksan</label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-zinc-700"><input type="checkbox" name="allow_unspecified_cause" value="1" @checked(old('allow_unspecified_cause', $selectedCampaign->allow_unspecified_cause))> Benarkan Belum Ditetapkan</label>
                    <textarea name="description" rows="2" class="rounded-xl border-zinc-300 md:col-span-2 xl:col-span-3">{{ old('description', $selectedCampaign->description) }}</textarea>
                    <button class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-bold text-white">Simpan Tetapan</button>
                </form>
            </section>

            <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">Publikasi Awam</p>
                        <h2 class="mt-1 text-lg font-black text-emerald-950">Buka carian nama murid dengan URL selamat</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-900/80">
                            Tindakan ini menerbitkan peserta layak sahaja, menukar nama paparan yang masih sama seperti nama penuh murid kepada alias, dan mengganti slug lama yang mengandungi nama murid.
                        </p>
                        <p class="mt-2 text-sm font-semibold text-emerald-950">
                            {{ number_format($publishStats['published']) }} / {{ number_format($publishStats['eligible']) }} peserta layak telah diterbitkan.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('system.jogathon.campaigns.publish-participants', $selectedCampaign) }}" class="grid min-w-full gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-emerald-200 sm:min-w-[28rem]">
                        @csrf
                        <label class="text-sm font-semibold text-zinc-700">Kelas
                            <select name="class_name" class="mt-1 w-full rounded-xl border-zinc-300">
                                <option value="">Semua kelas layak</option>
                                @foreach ($campaignClassNames as $className)
                                    <option value="{{ $className }}">{{ $className }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex items-center gap-2 text-sm font-semibold text-zinc-700">
                            <input type="checkbox" name="activate_campaign" value="1" @checked(! $selectedCampaign->isPubliclyAvailable())>
                            Aktifkan kempen untuk laman awam
                        </label>
                        <button class="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-900">Terbitkan dengan alias selamat</button>
                    </form>
                </div>
            </section>

            <section class="rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
                <div class="grid gap-5 lg:grid-cols-[.9fr_1.1fr]">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Import Roster Jogathon</p>
                        <h2 class="mt-1 text-lg font-black text-sky-950">Baca data murid daripada API sekolah</h2>
                        <p class="mt-2 text-sm leading-6 text-sky-900/80">
                            Import ini hanya menyimpan nama murid, kelas dan nama guru kelas. Data keluarga, penjaga, telefon, bayaran dan resit daripada API tidak disimpan ke dalam mini app Jogathon.
                        </p>
                        <p class="mt-3 rounded-xl bg-white/80 p-3 text-xs leading-5 text-sky-900 ring-1 ring-sky-200">
                            Endpoint API memerlukan kata carian. Untuk elak rate limit, import satu atau beberapa kelas dahulu dengan kata carian yang munasabah seperti <span class="font-mono">a,e,i,o,u,bin,binti</span>.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('system.jogathon.campaigns.roster-import.store', $selectedCampaign) }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-sky-200">
                        @csrf
                        <label class="text-sm font-semibold text-zinc-700">Endpoint API
                            <input name="endpoint" required value="{{ old('endpoint', 'https://sumbangan-pibg.sripetaling.edu.my/api/v1/payment-status/search') }}" class="mt-1 w-full rounded-xl border-zinc-300 text-sm">
                        </label>
                        <label class="text-sm font-semibold text-zinc-700">API Key
                            <input name="api_key" required type="password" autocomplete="off" placeholder="Bearer key daripada portal sekolah" class="mt-1 w-full rounded-xl border-zinc-300 text-sm">
                        </label>
                        <div class="grid gap-3 md:grid-cols-[8rem_1fr]">
                            <label class="text-sm font-semibold text-zinc-700">Tahun
                                <input type="number" name="year" required value="{{ old('year', now()->year) }}" class="mt-1 w-full rounded-xl border-zinc-300 text-sm">
                            </label>
                            <label class="text-sm font-semibold text-zinc-700">Kelas
                                <textarea name="class_names" required rows="2" placeholder="6 ALAMANDA&#10;5 AZALEA" class="mt-1 w-full rounded-xl border-zinc-300 text-sm">{{ old('class_names') }}</textarea>
                            </label>
                        </div>
                        <label class="text-sm font-semibold text-zinc-700">Kata carian
                            <textarea name="keywords" required rows="2" class="mt-1 w-full rounded-xl border-zinc-300 font-mono text-sm">{{ old('keywords', 'a,e,i,o,u,bin,binti') }}</textarea>
                        </label>
                        <label class="text-sm font-semibold text-zinc-700">Guru kelas, pilihan
                            <textarea name="teacher_mappings" rows="2" placeholder="6 ALAMANDA=Cikgu Aina&#10;5 AZALEA=Cikgu Farid" class="mt-1 w-full rounded-xl border-zinc-300 text-sm">{{ old('teacher_mappings') }}</textarea>
                        </label>
                        <label class="flex items-center gap-2 text-sm font-semibold text-zinc-700">
                            <input type="checkbox" name="provision_participants" value="1" @checked(old('provision_participants', true))>
                            Terus segarkan peserta Jogathon selepas import
                        </label>
                        <button class="rounded-xl bg-sky-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-900">Import roster minimal</button>
                    </form>
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-zinc-900">Tujuan Kempen</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($selectedCampaign->causes as $cause)
                        <form method="POST" action="{{ route('system.jogathon.causes.update', $cause) }}" class="grid gap-3 rounded-xl border border-zinc-200 p-3 md:grid-cols-[1fr_10rem_6rem_auto]">
                            @csrf @method('PATCH')
                            <input name="name" value="{{ $cause->name }}" class="rounded-xl border-zinc-300 text-sm">
                            <input type="number" name="target_amount_rm" step="0.01" value="{{ number_format($cause->target_amount_sen / 100, 2, '.', '') }}" class="rounded-xl border-zinc-300 text-sm">
                            <input type="number" name="sort_order" value="{{ $cause->sort_order }}" class="rounded-xl border-zinc-300 text-sm">
                            <label class="flex items-center gap-2 text-xs font-bold"><input type="checkbox" name="is_active" value="1" @checked($cause->is_active)> Aktif</label>
                            <button class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-bold md:col-start-4">Simpan</button>
                        </form>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('system.jogathon.causes.store', $selectedCampaign) }}" class="mt-4 grid gap-3 rounded-xl border border-dashed border-sky-300 bg-sky-50 p-3 md:grid-cols-[1fr_10rem_6rem_auto]">
                    @csrf
                    <input name="name" required placeholder="Tujuan baharu" class="rounded-xl border-zinc-300 text-sm">
                    <input type="number" name="target_amount_rm" required min="1" step="0.01" placeholder="Sasaran RM" class="rounded-xl border-zinc-300 text-sm">
                    <input type="number" name="sort_order" value="{{ $selectedCampaign->causes->max('sort_order') + 1 }}" class="rounded-xl border-zinc-300 text-sm">
                    <button class="rounded-xl bg-sky-700 px-3 py-2 text-xs font-bold text-white">Tambah</button>
                </form>
            </section>

            <section class="rounded-2xl border border-teal-200 bg-teal-50 p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-teal-700">Analitik Pautan Peserta</p>
                        <h2 class="mt-1 text-lg font-black text-teal-950">Rekod scan QR dan klik pautan</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-teal-900/80">
                            Rekod ini menyimpan sumber trafik secara agregat: QR, pautan terus, sosial atau referral. IP disimpan sebagai hash; nama penuh murid dan maklumat penjaga tidak direkod dalam analitik awam.
                        </p>
                    </div>
                    <div class="grid min-w-[16rem] grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                        @foreach (['qr' => 'QR', 'direct_link' => 'Direct', 'social' => 'Social', 'referral' => 'Referral'] as $source => $label)
                            <div class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-teal-200">
                                <p class="text-xs font-bold uppercase tracking-wide text-teal-700">{{ $label }}</p>
                                <p class="mt-1 text-2xl font-black text-teal-950">{{ number_format((int) ($visitStats[$source] ?? 0)) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-200 p-5"><h2 class="text-lg font-bold text-zinc-900">Peserta, Kelas dan Guru Kelas</h2><p class="mt-1 text-sm text-zinc-500">Nama awam dan slug kekal berasingan daripada ID murid. Semua peserta baharu bermula tidak diterbitkan.</p></div>
                @if ($participants?->isNotEmpty())
                    @php($activeCauses = $selectedCampaign->causes->where('is_active', true)->whereNull('archived_at')->values())
                    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-zinc-200 text-sm"><thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500"><tr><th class="px-4 py-3">Murid</th><th class="px-4 py-3">Kelas</th><th class="px-4 py-3">Guru Kelas</th><th class="px-4 py-3">Nombor Kad</th><th class="px-4 py-3">Slug Awam</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Kutipan Kad Fizikal</th></tr></thead><tbody class="divide-y divide-zinc-100">
                        @foreach ($participants as $participant)
                            @php($classKey = mb_strtoupper(trim((string) $participant->class_name_snapshot)))
                            <tr><td class="px-4 py-3 font-semibold text-zinc-900">{{ $participant->public_display_name }}</td><td class="px-4 py-3">{{ $participant->class_name_snapshot ?: '—' }}</td><td class="px-4 py-3">{{ $jogathonClassTeachers->get($classKey) ?: ($teachersByClass->get($classKey)?->pluck('name')->join(', ') ?: 'Belum dipadankan') }}</td><td class="px-4 py-3">
                                @can('enterJogathonPhysicalCollections')
                                    <form method="POST" action="{{ route('system.jogathon.participants.physical-card-number.update', $participant) }}" class="flex min-w-[14rem] gap-2">
                                        @csrf @method('PATCH')
                                        <input name="physical_card_number" value="{{ $participant->physical_card_number }}" placeholder="ssp-0001" pattern="ssp-[0-9]{4,8}" class="w-28 rounded-lg border-zinc-300 font-mono text-xs lowercase">
                                        <button class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-bold hover:bg-zinc-50">Simpan</button>
                                    </form>
                                    @if ($participant->physical_card_number && $selectedCampaign->isPubliclyAvailable() && $participant->isPubliclyVisible())
                                        <a href="{{ $participant->publicShortUrl() }}" target="_blank" rel="noopener" class="mt-2 block font-mono text-xs text-sky-700 underline">/{{ $participant->physical_card_number }}</a>
                                    @endif
                                @else
                                    <span class="font-mono text-xs">{{ $participant->physical_card_number ?: '—' }}</span>
                                @endcan
                            </td><td class="px-4 py-3 font-mono text-xs">@if ($selectedCampaign->isPubliclyAvailable() && $participant->isPubliclyVisible())<a href="{{ $participant->publicShortUrl() ?: route('jogathon.public.participants.show', [$selectedCampaign, $participant->publicUrlIdentifier()]) }}" target="_blank" rel="noopener" class="text-sky-700 underline">{{ $participant->publicUrlIdentifier() }}</a>@else{{ $participant->public_slug }}@endif</td><td class="px-4 py-3">{{ $participant->is_eligible ? 'Layak' : 'Tidak layak' }} · {{ $participant->is_published ? 'Diterbitkan' : 'Peribadi' }}</td><td class="px-4 py-3">
                                @can('enterJogathonPhysicalCollections')
                                    @if ($participant->is_eligible && ! $participant->participation_opt_out && $participant->withdrawn_at === null && $activeCauses->isNotEmpty())
                                        <form method="POST" action="{{ route('system.jogathon.participants.physical-contributions.store', $participant) }}" class="grid min-w-[28rem] gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 md:grid-cols-[6rem_1fr_8rem_auto]">
                                            @csrf
                                            <input type="number" name="amount_rm" min="1" step="0.01" placeholder="RM" required class="rounded-lg border-amber-200 text-xs">
                                            <select name="cause_id" required class="rounded-lg border-amber-200 text-xs">
                                                @foreach ($activeCauses as $cause)
                                                    <option value="{{ $cause->id }}">{{ $cause->name }}</option>
                                                @endforeach
                                            </select>
                                            <input name="collection_reference" maxlength="120" placeholder="Rujukan kad" class="rounded-lg border-amber-200 text-xs">
                                            <button class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white hover:bg-amber-700">Rekod</button>
                                            <input type="date" name="received_on" value="{{ now()->toDateString() }}" class="rounded-lg border-amber-200 text-xs">
                                            <input name="donor_display_name" maxlength="120" placeholder="Nama penyumbang / kosong" class="rounded-lg border-amber-200 text-xs md:col-span-2">
                                            <input name="note" maxlength="280" placeholder="Catatan ringkas" class="rounded-lg border-amber-200 text-xs">
                                        </form>
                                    @else
                                        <span class="text-xs text-zinc-500">Tidak tersedia untuk peserta ini.</span>
                                    @endif
                                @else
                                    <span class="text-xs text-zinc-500">Tiada akses.</span>
                                @endcan
                            </td></tr>
                        @endforeach
                    </tbody></table></div>
                    <div class="p-4">{{ $participants->links() }}</div>
                @else
                    <p class="p-5 text-sm text-zinc-500">Belum diprovision. Gunakan “Segarkan Peserta Aktif”.</p>
                @endif
            </section>
        @endif
    </div>
</x-layouts::app>
