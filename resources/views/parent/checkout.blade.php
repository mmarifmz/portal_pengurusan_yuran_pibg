<x-layouts::app :title="__('Bayaran PIBG')">
    @php
        $baseAmount = (float) ($checkoutBaseAmount ?? $familyBilling->outstanding_amount);
        $prefilledDonation = (float) ($defaultDonation ?? 0);
        $prefilledTotal = $baseAmount + $prefilledDonation;
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap gap-4">
            <div
                class="flex-1 rounded-3xl border p-6 shadow-lg"
                style="border-color:#7dd3a9;background:#1f8b5d;background-image:linear-gradient(140deg,#1f8b5d 0%,#166a4c 100%);color:#ffffff;box-shadow:0 20px 44px rgba(22,106,76,0.28);"
            >
                <h2 class="text-3xl font-extrabold tracking-tight">
                    {{ ! empty($alreadyPaidCurrentYear) ? 'Terima Kasih & Tahniah' : 'Bil Bayaran' }}
                </h2>
                <div class="mt-4 rounded-2xl border p-4" style="border-color:rgba(255,255,255,0.26);background:rgba(255,255,255,0.12);">
                    @if (! empty($alreadyPaidCurrentYear))
                        <p class="text-sm font-semibold" style="color:rgba(255,255,255,0.95);">Yuran PIBG {{ $familyBilling->billing_year }} telah dijelaskan.</p>
                        <p class="mt-1 text-3xl font-black tracking-tight">Terima kasih, bayaran yuran tahunan telah selesai.</p>
                    @else
                        <p class="text-sm" style="color:rgba(255,255,255,0.9);">Yuran PIBG {{ $familyBilling->billing_year }}</p>
                        <p class="mt-1 text-5xl font-black tracking-tight">RM {{ number_format($baseAmount, 2) }}</p>
                    @endif
                </div>
                <div class="mt-5 space-y-2 text-sm" style="color:#ecfdf3;">
                    <p><span class="font-semibold">Kod keluarga:</span> {{ $familyBilling->family_code }}</p>
                    <p><span class="font-semibold">Jumlah anak:</span> {{ $familyChildren->count() }}</p>
                    @if (! empty($alreadyPaidCurrentYear))
                        <p style="color:rgba(236,253,243,0.92);">Anda boleh memberi sumbangan tambahan pada bila-bila masa sepanjang tahun.</p>
                        <p style="color:rgba(236,253,243,0.92);">Sila nyatakan niat/tujuan sumbangan tambahan di ruangan yang disediakan.</p>
                    @else
                        <p style="color:rgba(236,253,243,0.92);">Yuran asas dikenakan sekali setahun bagi setiap keluarga.</p>
                    @endif
                </div>
            </div>

            <div class="flex-1 rounded-3xl border border-zinc-200 bg-white p-6 shadow-lg">
                <h3 class="text-3xl font-extrabold tracking-tight text-zinc-800">Maklumat Pembayaran</h3>
                <p class="mt-2 text-sm text-zinc-600">Sistem akan memindahkan anda ke platform ToyyibPay untuk pilihan FPX atau kad.</p>
                @if (! empty($alreadyPaidCurrentYear))
                    <div class="mt-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        Terima kasih dan tahniah. Yuran PIBG tahun semasa telah selesai, dan anda masih boleh memberi <span class="font-extrabold">Sumbangan Tambahan</span> sepanjang tahun.
                    </div>
                @endif
                @if (! empty($isTesterMode))
                    <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                        Akaun Tester Treasury: transaksi ini akan dicipta sebagai RM {{ number_format((float) ($testerAmount ?? 1), 2) }}.
                    </div>
                @endif
                <div class="mt-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-relaxed text-sky-900">
                    @if (! empty($alreadyPaidCurrentYear))
                        Anda boleh teruskan sumbangan tambahan pada bila-bila masa sepanjang tahun.
                        <span class="font-semibold">Sila nyatakan niat/tujuan sumbangan tambahan</span> sebelum membuat bayaran.
                    @else
                        Jumlah akhir = <span class="font-semibold">Yuran asas</span> + <span class="font-semibold">Sumbangan tambahan (pilihan)</span>.
                        Sumbangan tambahan membantu aktiviti PIBG sekolah.
                    @endif
                </div>

                <form action="{{ route('parent.payments.create', $familyBilling) }}" method="POST" class="mt-4 space-y-4">
                    @csrf
                    @if ($errors->has('payment_gateway'))
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {{ $errors->first('payment_gateway') }}
                        </div>
                    @endif
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Nama Ibu Bapa / Penjaga</label>
                        <input
                            name="payer_name"
                            type="text"
                            required
                            class="w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm"
                            placeholder="Contoh: Nurul Aini binti Ahmad"
                            value="{{ old('payer_name') }}"
                        >
                        <x-auth-session-status class="text-xs text-red-600" :status="$errors->first('payer_name')" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Email</label>
                        <input
                            name="payer_email"
                            type="email"
                            required
                            class="w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm"
                            placeholder="Contoh: ibu.bapa@email.com"
                            value="{{ old('payer_email') }}"
                        >
                        <x-auth-session-status class="text-xs text-red-600" :status="$errors->first('payer_email')" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">No Telefon</label>
                        <input name="payer_phone" type="text" required class="w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm" value="{{ old('payer_phone', $defaultPhone) }}">
                        <x-auth-session-status class="text-xs text-red-600" :status="$errors->first('payer_phone')" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Sumbangan Tambahan (Pilihan)</label>
                        <div class="flex flex-wrap gap-2 text-xs">
                            @foreach([50,100,250,500,1000] as $preset)
                                <button type="button" class="rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-left text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 js-presets" data-amount="{{ $preset }}">RM{{ $preset }}</button>
                            @endforeach
                            <button type="button" class="rounded-full border border-zinc-300 bg-white px-3 py-1 text-left text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50 js-reset-donation">
                                Reset ke RM{{ number_format($baseAmount, 0) }}
                            </button>
                        </div>
                        <div class="flex w-full overflow-hidden rounded-lg border border-zinc-300">
                            <span class="inline-flex items-center border-r border-zinc-300 bg-zinc-50 px-4 py-3 text-sm font-semibold text-zinc-700">RM</span>
                            <input
                                name="donation_custom"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full border-0 px-4 py-3 text-sm outline-none focus:ring-0"
                                placeholder="0"
                                value="{{ old('donation_custom', $prefilledDonation > 0 ? number_format($prefilledDonation, 2, '.', '') : '') }}"
                            >
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Niat / Tujuan Sumbangan (Pilihan)</label>
                            <textarea
                                name="donation_intention"
                                rows="2"
                                maxlength="500"
                                class="w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm"
                                placeholder="{{ ! empty($alreadyPaidCurrentYear) ? 'Sila nyatakan niat sumbangan tambahan anda. Contoh: Sumbangan untuk aktiviti kelas dan kebajikan murid.' : 'Contoh: Sumbangan tambahan untuk aktiviti kelas dan kebajikan murid.' }}"
                            >{{ old('donation_intention') }}</textarea>
                            <x-auth-session-status class="text-xs text-red-600" :status="$errors->first('donation_intention')" />
                        </div>
                    </div>
                    <input type="hidden" name="donation_preset" value="">
                    <input type="hidden" name="total_amount" value="{{ number_format($prefilledTotal, 2, '.', '') }}">

                    <div class="space-y-2 rounded-2xl border border-zinc-200 bg-[color:var(--brand-soft)] px-4 py-3 text-sm">
                        <div class="flex items-center justify-between text-zinc-600">
                            <span>Yuran Asas</span>
                            <strong id="baseAmountLabel">RM {{ number_format($baseAmount, 2) }}</strong>
                        </div>
                        <div class="flex items-center justify-between text-zinc-600">
                            <span>Sumbangan Tambahan</span>
                            <strong id="donationAmountLabel">RM {{ number_format($prefilledDonation, 2) }}</strong>
                        </div>
                        <div class="flex items-center justify-between border-t border-zinc-200 pt-2 text-base text-zinc-900">
                            <span class="font-semibold">Jumlah Bayaran</span>
                            <strong class="text-xl font-extrabold text-[color:var(--brand-green)]" id="totalAmountLabel">RM {{ number_format($prefilledTotal, 2) }}</strong>
                        </div>
                    </div>
                    <button
                        type="submit"
                        class="w-full rounded-2xl px-4 py-3 text-base font-semibold text-white"
                        style="background:#1f8b5d;box-shadow:0 14px 28px rgba(31,139,93,0.30);"
                    >
                        Sahkan & Bayar
                    </button>
                </form>
            </div>
        </div>

        <section class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-lg">
            <h3 class="text-base font-bold text-[color:var(--brand-forest)]">Senarai Anak</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach($familyChildren as $child)
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm">
                        <p class="font-semibold text-zinc-900">{{ $child->full_name }}</p>
                        <p class="text-xs text-zinc-500">{{ $child->class_name }}</p>
                        <p class="text-[0.65rem] text-zinc-500">No. Murid: {{ $child->student_no }}</p>
                    </div>
                @endforeach
            </div>

        </section>

        <section class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-lg">
            <h3 class="text-base font-bold text-[color:var(--brand-forest)]">5 Cubaan Bayaran Terkini</h3>
            <div class="mt-3 space-y-2">
                @forelse ($recentPaymentAttempts as $attempt)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-700">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold text-zinc-900">{{ $attempt->external_order_display }}</span>
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[0.65rem] font-semibold text-zinc-600">{{ ucfirst((string) $attempt->status) }}</span>
                        </div>
                        <p class="mt-1">{{ $attempt->created_at_for_display?->format('d M Y H:i') ?? '-' }}, RM {{ number_format((float) $attempt->amount, 2) }}</p>
                    </div>
                @empty
                    <p class="text-xs text-zinc-500">Belum ada cubaan bayaran direkodkan untuk keluarga ini.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-lg">
            <h3 class="text-base font-bold text-[color:var(--brand-forest)]">Sejarah Bayaran Tahun {{ $lastYear }}</h3>
            <p class="mt-2 text-sm text-zinc-700">Jumlah dibayar berjaya: <span class="font-bold text-zinc-900">RM {{ number_format((float) ($lastYearPaidTotal ?? 0), 2) }}</span></p>
            <p class="mt-1 text-sm text-zinc-700">Jumlah sumbangan berjaya: <span class="font-bold text-zinc-900">RM {{ number_format((float) $lastYearContributionTotal, 2) }}</span></p>
            <div class="mt-3 space-y-2">
                @forelse ($lastYearPaymentHistory as $payment)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-700">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold text-zinc-900">{{ $payment['reference'] }}</span>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[0.65rem] font-semibold text-emerald-700">Berjaya</span>
                        </div>
                        <p class="mt-1">
                            {{ $payment['paid_at']?->format('d M Y H:i') ?? '-' }},
                            Bayaran RM {{ number_format((float) ($payment['paid_amount'] ?? 0), 2) }}
                            @if ((float) ($payment['donation_amount'] ?? 0) > 0)
                                · Sumbangan RM {{ number_format((float) $payment['donation_amount'], 2) }}
                            @endif
                        </p>
                    </div>
                @empty
                    <p class="text-xs text-zinc-500">Tiada rekod bayaran berjaya untuk tahun {{ $lastYear }}.</p>
                @endforelse
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                const baseAmount = {{ json_encode($baseAmount) }};
                const presetInput = document.querySelector('input[name="donation_preset"]');
                const customInput = document.querySelector('input[name="donation_custom"]');
                const donationLabel = document.getElementById('donationAmountLabel');
                const totalLabel = document.getElementById('totalAmountLabel');
                const totalAmountInput = document.querySelector('input[name="total_amount"]');
                const presetButtons = document.querySelectorAll('.js-presets');
                const resetButton = document.querySelector('.js-reset-donation');

                const toMoney = (value) => `RM ${Number(value).toFixed(2)}`;
                const getDonationValue = () => {
                    const raw = customInput.value.trim();
                    const amount = Number(raw);
                    return Number.isFinite(amount) && amount > 0 ? amount : 0;
                };

                const refreshBreakdown = () => {
                    const donation = getDonationValue();
                    const total = baseAmount + donation;
                    donationLabel.textContent = toMoney(donation);
                    totalLabel.textContent = toMoney(total);
                    totalAmountInput.value = total.toFixed(2);
                };

                presetButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const amount = button.dataset.amount;
                        presetInput.value = amount;
                        customInput.value = amount;
                        refreshBreakdown();
                    });
                });

                resetButton?.addEventListener('click', () => {
                    presetInput.value = '';
                    customInput.value = '';
                    refreshBreakdown();
                });

                customInput.addEventListener('input', () => {
                    presetInput.value = '';
                    refreshBreakdown();
                });

                refreshBreakdown();
            })();
        </script>
    @endpush
</x-layouts::app>
