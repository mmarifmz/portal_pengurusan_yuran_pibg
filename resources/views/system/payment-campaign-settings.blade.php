<x-layouts::app :title="__('Tetapan Kempen Bayaran')">
    <div class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">System Admin</p>
            <h1 class="text-2xl font-bold text-zinc-900">Tetapan Kempen Bayaran</h1>
            <p class="text-sm text-zinc-500">Kawal pilihan Bayaran Penuh dan Bayaran Ansuran yang dibenarkan untuk ibu bapa semasa kempen aktif.</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">{{ $formHeading }}</h2>
                    <p class="text-sm text-zinc-500">{{ $formDescription }}</p>
                </div>

                @if ($formMode !== 'latest')
                    <a
                        href="{{ route('system.payment-campaign-settings.index') }}"
                        class="inline-flex items-center rounded-xl border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900"
                    >
                        Kembali Ke Kempen Semasa
                    </a>
                @endif
            </div>

            @if ($selectedHistorySetting && $formMode === 'edit')
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Anda sedang mengedit kempen <span class="font-semibold">{{ $selectedHistorySetting->campaign_name }}</span>. Perubahan akan dikemas kini pada rekod yang sama.
                </div>
            @elseif ($selectedHistorySetting && $formMode === 'duplicate')
                <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                    Anda sedang menyalin kempen <span class="font-semibold">{{ $selectedHistorySetting->campaign_name }}</span>. Simpanan ini akan mencipta rekod kempen baharu.
                </div>
            @endif

            <form method="POST" action="{{ route('system.payment-campaign-settings.save') }}" class="grid gap-4">
                @csrf

                <input type="hidden" name="setting_id" value="{{ old('setting_id', $formSettingId) }}">

                <label class="text-sm font-medium text-zinc-700">
                    Campaign Name
                    <input
                        name="campaign_name"
                        type="text"
                        required
                        value="{{ old('campaign_name', $currentSetting?->campaign_name) }}"
                        class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="inline-flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-700">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $currentSetting?->is_active))>
                        Aktifkan Kempen
                    </label>
                    <label class="inline-flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-700">
                        <input type="checkbox" name="allow_single_payment" value="1" @checked(old('allow_single_payment', $currentSetting?->allow_single_payment ?? true))>
                        Bayaran Penuh
                    </label>
                </div>

                <label class="inline-flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-700">
                    <input id="allow_split_payment" type="checkbox" name="allow_split_payment" value="1" @checked(old('allow_split_payment', $currentSetting?->allow_split_payment))>
                    Bayaran Ansuran
                </label>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 p-4">
                        <label class="inline-flex items-center gap-3 text-sm font-medium text-zinc-700">
                            <input id="allow_split_2" type="checkbox" name="allow_split_2" value="1" @checked(old('allow_split_2', $currentSetting?->allow_split_2))>
                            Ansuran 2 Kali
                        </label>

                        <label class="mt-4 block text-sm font-medium text-zinc-700">
                            Split 2 Visibility
                            <select name="split_2_visibility" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <option value="all" @selected(old('split_2_visibility', $currentSetting?->split_2_visibility ?? 'all') === 'all')>Untuk Semua</option>
                                <option value="social_tag" @selected(old('split_2_visibility', $currentSetting?->split_2_visibility) === 'social_tag')>Berdasarkan Tag Sosial</option>
                            </select>
                        </label>

                        <label class="mt-4 block text-sm font-medium text-zinc-700">
                            Tag Sosial
                            <select
                                name="split_2_social_tag_id"
                                class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            >
                                <option value="">Pilih Tag Sosial</option>
                                @foreach ($socialTags as $socialTag)
                                    <option
                                        value="{{ $socialTag->id }}"
                                        @selected((string) old('split_2_social_tag_id', $currentSetting?->split_2_social_tag_id) === (string) $socialTag->id)
                                    >
                                        {{ $socialTag->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="rounded-xl border border-zinc-200 p-4">
                        <label class="inline-flex items-center gap-3 text-sm font-medium text-zinc-700">
                            <input id="allow_split_3" type="checkbox" name="allow_split_3" value="1" @checked(old('allow_split_3', $currentSetting?->allow_split_3))>
                            Ansuran 3 Kali
                        </label>

                        <label class="mt-4 block text-sm font-medium text-zinc-700">
                            Split 3 Visibility
                            <select name="split_3_visibility" class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <option value="all" @selected(old('split_3_visibility', $currentSetting?->split_3_visibility ?? 'all') === 'all')>Untuk Semua</option>
                                <option value="social_tag" @selected(old('split_3_visibility', $currentSetting?->split_3_visibility) === 'social_tag')>Berdasarkan Tag Sosial</option>
                            </select>
                        </label>

                        <label class="mt-4 block text-sm font-medium text-zinc-700">
                            Tag Sosial
                            <select
                                name="split_3_social_tag_id"
                                class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            >
                                <option value="">Pilih Tag Sosial</option>
                                @foreach ($socialTags as $socialTag)
                                    <option
                                        value="{{ $socialTag->id }}"
                                        @selected((string) old('split_3_social_tag_id', $currentSetting?->split_3_social_tag_id) === (string) $socialTag->id)
                                    >
                                        {{ $socialTag->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="text-sm font-medium text-zinc-700">
                        Tarikh Mula
                        <input
                            name="effective_from"
                            type="datetime-local"
                            value="{{ old('effective_from', optional($currentSetting?->effective_from)->format('Y-m-d\\TH:i')) }}"
                            class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>

                    <label class="text-sm font-medium text-zinc-700">
                        Tarikh Tamat
                        <input
                            name="effective_until"
                            type="datetime-local"
                            value="{{ old('effective_until', optional($currentSetting?->effective_until)->format('Y-m-d\\TH:i')) }}"
                            class="mt-1 w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>
                </div>

                <div>
                    <button type="submit" class="inline-flex items-center rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-700">
                        Simpan Tetapan Kempen
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Sejarah Kempen</h2>
                    <p class="text-sm text-zinc-500">Satu kempen aktif sahaja dibenarkan pada satu masa.</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Kempen</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Tempoh</th>
                            <th class="px-4 py-3">Pilihan</th>
                            <th class="px-4 py-3">Kemaskini</th>
                            <th class="px-4 py-3">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($history as $setting)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-zinc-900">{{ $setting->campaign_name }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ $setting->is_active ? 'Aktif' : 'Tidak Aktif' }}</td>
                                <td class="px-4 py-3 text-zinc-700">
                                    {{ $setting->effective_from?->format('d M Y H:i') ?: '-' }}
                                    -
                                    {{ $setting->effective_until?->format('d M Y H:i') ?: '-' }}
                                </td>
                                <td class="px-4 py-3 text-zinc-700">
                                    {{ $setting->allow_single_payment ? 'Bayaran Penuh' : '-' }}
                                    @if ($setting->allow_split_payment && $setting->allow_split_2)
                                        | Ansuran 2 Kali{{ $setting->split_2_visibility === 'social_tag' ? ' · '.($setting->split2SocialTag?->name ?? $setting->split_2_social_tag ?? '-') : '' }}
                                    @endif
                                    @if ($setting->allow_split_payment && $setting->allow_split_3)
                                        | Ansuran 3 Kali{{ $setting->split_3_visibility === 'social_tag' ? ' · '.($setting->split3SocialTag?->name ?? $setting->split_3_social_tag ?? '-') : '' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-700">{{ $setting->updated_at?->format('d M Y H:i') ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a
                                            href="{{ route('system.payment-campaign-settings.index', ['campaign_id' => $setting->id, 'mode' => 'edit']) }}"
                                            class="inline-flex items-center rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-300 hover:text-zinc-900"
                                        >
                                            Edit
                                        </a>
                                        <a
                                            href="{{ route('system.payment-campaign-settings.index', ['campaign_id' => $setting->id, 'mode' => 'duplicate']) }}"
                                            class="inline-flex items-center rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:border-emerald-300 hover:text-emerald-800"
                                        >
                                            Duplicate
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-zinc-500">Belum ada kempen bayaran direkodkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const splitToggle = document.getElementById('allow_split_payment');
            const splitChildren = [
                document.getElementById('allow_split_2'),
                document.getElementById('allow_split_3'),
            ].filter(Boolean);

            if (! splitToggle || splitChildren.length === 0) {
                return;
            }

            const syncParentFromChildren = () => {
                if (splitChildren.some((checkbox) => checkbox.checked)) {
                    splitToggle.checked = true;
                }
            };

            const syncChildrenFromParent = () => {
                if (splitToggle.checked) {
                    return;
                }

                splitChildren.forEach((checkbox) => {
                    checkbox.checked = false;
                });
            };

            splitChildren.forEach((checkbox) => {
                checkbox.addEventListener('change', syncParentFromChildren);
            });

            splitToggle.addEventListener('change', syncChildrenFromParent);
        });
    </script>
</x-layouts::app>
