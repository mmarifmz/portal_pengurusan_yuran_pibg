<x-layouts::app :title="__('QR Kempen & Analitik')">
    @php
        $formCampaign = $editCampaign;
        $formAction = $formCampaign
            ? route('system.qr-campaigns.update', $formCampaign)
            : route('system.qr-campaigns.store');
        $purposeLabels = [
            'payment' => 'Bayaran PIBG',
            'donation' => 'Sumbangan',
            'event' => 'Acara Sekolah',
            'programme' => 'Program',
        ];
        $destinationLabels = [
            'payment_directory' => 'Direktori Bayaran',
            'parent_login' => 'Log Masuk Ibu Bapa',
            'school_calendar' => 'Takwim Sekolah',
            'custom_internal' => 'Borang / Laluan Portal',
        ];
    @endphp

    <div class="space-y-6">
        <header class="overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-emerald-800 to-teal-700 p-6 text-white shadow-lg">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.28em] text-emerald-200">System Admin</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight">QR Kempen & Analitik</h1>
                    <p class="mt-2 text-sm leading-6 text-emerald-50/90">
                        Jana poster QR berjejak untuk bayaran, sumbangan, acara dan program. Setiap imbasan direkod berasingan daripada bayaran yang benar-benar disahkan.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/20 bg-white/10 px-4 py-3 text-right backdrop-blur">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-100">Tempoh atribusi</p>
                    <p class="mt-1 text-lg font-bold">30 hari</p>
                </div>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-bold">Sila semak maklumat berikut:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-sky-700">Jumlah Imbasan</p>
                <p class="mt-2 text-3xl font-black text-sky-950">{{ number_format($totalScans) }}</p>
                <p class="mt-1 text-xs text-sky-700">Semua aktiviti QR</p>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">Pengimbas Unik</p>
                <p class="mt-2 text-3xl font-black text-indigo-950">{{ number_format($uniqueScans) }}</p>
                <p class="mt-1 text-xs text-indigo-700">Anggaran peranti berbeza</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Bayaran Disahkan</p>
                <p class="mt-2 text-3xl font-black text-emerald-950">{{ number_format($confirmedPayments) }}</p>
                <p class="mt-1 text-xs text-emerald-700">Transaksi berjaya sahaja</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Nilai Disahkan</p>
                <p class="mt-2 text-3xl font-black text-amber-950">RM {{ number_format($confirmedAmount, 2) }}</p>
                <p class="mt-1 text-xs text-amber-700">Daripada sumber QR</p>
            </div>
            <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-violet-700">Kadar Penukaran</p>
                <p class="mt-2 text-3xl font-black text-violet-950">{{ number_format($conversionRate, 1) }}%</p>
                <p class="mt-1 text-xs text-violet-700">Bayaran disahkan / imbasan</p>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-zinc-900">Aktiviti 14 Hari</h2>
                    <p class="text-sm text-zinc-500">Biru menunjukkan imbasan; hijau menunjukkan bayaran disahkan.</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-semibold text-zinc-600">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span> Imbasan</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Bayaran</span>
                </div>
            </div>
            <div class="mt-5 grid min-h-48 grid-cols-7 items-end gap-2 sm:grid-cols-14">
                @foreach ($trend as $day)
                    <div class="flex min-w-0 flex-col items-center gap-2" title="{{ $day['date'] }}: {{ $day['scans'] }} imbasan, {{ $day['payments'] }} bayaran">
                        <div class="flex h-32 w-full items-end justify-center gap-1 rounded-lg bg-zinc-50 px-1">
                            <div class="w-2.5 rounded-t bg-sky-500" style="height: {{ max(3, ($day['scans'] / $trendMax) * 100) }}%"></div>
                            <div class="w-2.5 rounded-t bg-emerald-500" style="height: {{ max(3, ($day['payments'] / $trendMax) * 100) }}%"></div>
                        </div>
                        <span class="truncate text-[10px] font-medium text-zinc-500">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-zinc-900">{{ $formCampaign ? 'Kemaskini QR Kempen' : 'Jana QR Kempen Baharu' }}</h2>
                    <p class="text-sm text-zinc-500">Sumber kelas, lokasi dan saluran akan kekal bersama rekod imbasan serta transaksi.</p>
                </div>
                @if ($formCampaign)
                    <a href="{{ route('system.qr-campaigns.index') }}" class="rounded-xl border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
                        QR Baharu
                    </a>
                @endif
            </div>

            <form method="POST" action="{{ $formAction }}" class="mt-5 grid gap-4">
                @csrf
                @if ($formCampaign)
                    @method('PATCH')
                @endif

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="text-sm font-semibold text-zinc-700">
                        Nama QR Kempen
                        <input name="name" required maxlength="255" value="{{ old('name', $formCampaign?->name) }}" placeholder="Contoh: Bayaran PIBG 2026 - 6 Bestari" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </label>
                    <label class="text-sm font-semibold text-zinc-700">
                        Tujuan
                        <select name="purpose" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            @foreach ($purposeLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('purpose', $formCampaign?->purpose ?? 'payment') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="text-sm font-semibold text-zinc-700">
                        Destinasi Portal
                        <select id="qr-destination-type" name="destination_type" required class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            @foreach ($destinationLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('destination_type', $formCampaign?->destination_type ?? 'payment_directory') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-xs font-normal text-zinc-500">Pilih direktori atau borang yang betul. Destinasi luar portal disekat.</span>
                    </label>
                    <label id="qr-custom-path-wrap" class="text-sm font-semibold text-zinc-700">
                        Laluan Borang / Portal
                        <input name="destination_path" value="{{ old('destination_path', $formCampaign?->destination_type === 'custom_internal' ? $formCampaign->destination_path : '') }}" placeholder="/review-payment/123" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2.5 font-mono text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        <span class="mt-1 block text-xs font-normal text-zinc-500">Mesti bermula dengan / dan berada dalam portal ini.</span>
                    </label>
                </div>

                <label class="text-sm font-semibold text-zinc-700">
                    Kempen Bayaran Berkaitan
                    <select name="payment_campaign_setting_id" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        <option value="">Tiada / bukan kempen bayaran</option>
                        @foreach ($paymentCampaignSettings as $paymentCampaign)
                            <option value="{{ $paymentCampaign->id }}" @selected((string) old('payment_campaign_setting_id', $formCampaign?->payment_campaign_setting_id) === (string) $paymentCampaign->id)>
                                {{ $paymentCampaign->campaign_name }}{{ $paymentCampaign->is_active ? ' (Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="grid gap-4 md:grid-cols-3">
                    <label class="text-sm font-semibold text-zinc-700">
                        Kelas
                        <input name="class_name" list="qr-class-options" value="{{ old('class_name', $formCampaign?->class_name) }}" placeholder="Contoh: 6 Bestari" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        <datalist id="qr-class-options">
                            @foreach ($classOptions as $className)
                                <option value="{{ $className }}"></option>
                            @endforeach
                        </datalist>
                    </label>
                    <label class="text-sm font-semibold text-zinc-700">
                        Lokasi
                        <input name="location_name" value="{{ old('location_name', $formCampaign?->location_name) }}" placeholder="Contoh: Dewan Terbuka" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </label>
                    <label class="text-sm font-semibold text-zinc-700">
                        Saluran Edaran
                        <input name="distribution_channel" value="{{ old('distribution_channel', $formCampaign?->distribution_channel) }}" placeholder="Contoh: WhatsApp Kelas" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </label>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-800">Kandungan Poster A4</p>
                    <div class="mt-3 grid gap-4 lg:grid-cols-2">
                        <label class="text-sm font-semibold text-zinc-700">
                            Tajuk Poster
                            <input name="poster_title" required value="{{ old('poster_title', $formCampaign?->poster_title ?? 'Jom Selesaikan Sumbangan PIBG') }}" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        </label>
                        <label class="text-sm font-semibold text-zinc-700">
                            Subtajuk
                            <input name="poster_subtitle" value="{{ old('poster_subtitle', $formCampaign?->poster_subtitle) }}" placeholder="Mudah, selamat dan terus melalui portal rasmi" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        </label>
                    </div>
                    <label class="mt-4 block text-sm font-semibold text-zinc-700">
                        Arahan Tindakan
                        <input name="call_to_action" required value="{{ old('call_to_action', $formCampaign?->call_to_action ?? 'Imbas untuk teruskan') }}" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="text-sm font-semibold text-zinc-700">
                        Aktif Mulai
                        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($formCampaign?->starts_at)->format('Y-m-d\\TH:i')) }}" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </label>
                    <label class="text-sm font-semibold text-zinc-700">
                        Tamat Pada
                        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', optional($formCampaign?->ends_at)->format('Y-m-d\\TH:i')) }}" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $formCampaign?->is_active ?? true))>
                        Aktifkan pautan QR
                    </label>
                    <button type="submit" class="rounded-xl bg-emerald-800 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700">
                        {{ $formCampaign ? 'Simpan Perubahan' : 'Jana QR & Poster' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 p-5">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-zinc-900">Prestasi Mengikut QR</h2>
                        <p class="text-sm text-zinc-500">Bandingkan kelas, lokasi, poster dan saluran edaran pada satu aras.</p>
                    </div>
                    <form method="GET" action="{{ route('system.qr-campaigns.index') }}" class="flex flex-wrap gap-2">
                        <input type="search" name="q" value="{{ $search }}" placeholder="Cari kempen / kelas / lokasi" class="rounded-xl border border-zinc-300 px-3 py-2 text-sm">
                        <select name="purpose" class="rounded-xl border border-zinc-300 px-3 py-2 text-sm">
                            <option value="all">Semua Tujuan</option>
                            @foreach ($purposeLabels as $value => $label)
                                <option value="{{ $value }}" @selected($purpose === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-bold text-white">Tapis</button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-bold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">QR Kempen</th>
                            <th class="px-4 py-3">Sumber</th>
                            <th class="px-4 py-3">Destinasi</th>
                            <th class="px-4 py-3 text-right">Imbasan</th>
                            <th class="px-4 py-3 text-right">Unik</th>
                            <th class="px-4 py-3 text-right">Bayaran</th>
                            <th class="px-4 py-3 text-right">Penukaran</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($campaigns as $campaign)
                            @php
                                $campaignConversion = $campaign->scans_count > 0
                                    ? ($campaign->confirmed_payments_count / $campaign->scans_count) * 100
                                    : 0;
                            @endphp
                            <tr class="align-top hover:bg-zinc-50/70">
                                <td class="px-4 py-4">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $campaign->isAvailable() ? 'bg-emerald-500' : 'bg-zinc-300' }}"></span>
                                        <div>
                                            <p class="font-bold text-zinc-900">{{ $campaign->name }}</p>
                                            <p class="mt-1 text-xs text-zinc-500">{{ $purposeLabels[$campaign->purpose] ?? $campaign->purpose }} · {{ $campaign->short_code }}</p>
                                            <div class="mt-2 flex max-w-xs items-center gap-1 rounded-lg bg-zinc-100 px-2 py-1 font-mono text-[11px] text-zinc-700">
                                                <span class="truncate">{{ $campaign->shortUrl() }}</span>
                                                <button type="button" data-copy-url="{{ $campaign->shortUrl() }}" class="shrink-0 font-sans font-bold text-emerald-700">Salin</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-zinc-700">
                                    <p>{{ $campaign->class_name ?: 'Semua kelas' }}</p>
                                    <p class="text-xs text-zinc-500">{{ $campaign->location_name ?: 'Tiada lokasi' }}</p>
                                    <p class="text-xs text-zinc-500">{{ $campaign->distribution_channel ?: 'Tiada saluran' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-zinc-800">{{ $destinationLabels[$campaign->destination_type] ?? $campaign->destination_type }}</p>
                                    <p class="mt-1 max-w-xs break-all font-mono text-xs text-zinc-500">{{ $campaign->destination_path }}</p>
                                    @if ($campaign->paymentCampaignSetting)
                                        <p class="mt-1 text-xs text-emerald-700">{{ $campaign->paymentCampaignSetting->campaign_name }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right font-bold text-sky-700">{{ number_format($campaign->scans_count) }}</td>
                                <td class="px-4 py-4 text-right font-semibold text-indigo-700">{{ number_format($campaign->unique_scans_count) }}</td>
                                <td class="px-4 py-4 text-right">
                                    <p class="font-bold text-emerald-700">{{ number_format($campaign->confirmed_payments_count) }}</p>
                                    <p class="text-xs text-zinc-500">RM {{ number_format((float) $campaign->confirmed_amount, 2) }}</p>
                                </td>
                                <td class="px-4 py-4 text-right font-bold text-violet-700">{{ number_format($campaignConversion, 1) }}%</td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-1.5">
                                        <a href="{{ route('system.qr-campaigns.qr-image', $campaign) }}" class="rounded-lg border border-zinc-300 px-2.5 py-1.5 text-xs font-bold text-zinc-700 hover:bg-zinc-50">PNG</a>
                                        <a href="{{ route('system.qr-campaigns.poster', $campaign) }}" class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-100">A4 PDF</a>
                                        <a href="{{ route('system.qr-campaigns.index', ['edit' => $campaign->id]) }}" class="rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1.5 text-xs font-bold text-emerald-800 hover:bg-emerald-100">Edit</a>
                                        <form method="POST" action="{{ route('system.qr-campaigns.toggle', $campaign) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="rounded-lg border border-zinc-300 px-2.5 py-1.5 text-xs font-bold text-zinc-700 hover:bg-zinc-50">
                                                {{ $campaign->is_active ? 'Nyahaktif' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-zinc-500">Belum ada QR kempen untuk paparan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const destination = document.getElementById('qr-destination-type');
            const customPath = document.getElementById('qr-custom-path-wrap');
            const syncCustomPath = () => customPath?.classList.toggle('hidden', destination?.value !== 'custom_internal');
            destination?.addEventListener('change', syncCustomPath);
            syncCustomPath();

            document.querySelectorAll('[data-copy-url]').forEach((button) => {
                button.addEventListener('click', async () => {
                    await navigator.clipboard.writeText(button.dataset.copyUrl || '');
                    const original = button.textContent;
                    button.textContent = 'Disalin';
                    setTimeout(() => button.textContent = original, 1400);
                });
            });
        })();
    </script>
</x-layouts::app>
