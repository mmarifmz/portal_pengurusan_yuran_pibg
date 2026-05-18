<x-layouts::app :title="__('Bayaran PIBG')">
    @php
        $baseAmount = (float) ($checkoutBaseAmount ?? $familyBilling->outstanding_amount);
        $prefilledDonation = (float) ($defaultDonation ?? 0);
        $prefilledTotal = $baseAmount + $prefilledDonation;
        $showInstallmentPlanner = empty($alreadyPaidCurrentYear) && (float) $familyBilling->outstanding_amount > 0;
        $showPlanSelector = $showInstallmentPlanner && (! $paymentPlan || ! empty($forcePlanSelection));
        $paymentGatewayNotice = $paymentGatewaySetting->parentPaymentNotice();
        $showGatewayMethods = $paymentGatewaySetting->enable_fpx || $paymentGatewaySetting->enable_duitnow_qr;
    @endphp

    <style>
        .change-plan-box {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .btn-change-plan {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #ffffff;
            color: #047857;
            border: 2px solid #10b981;
            border-radius: 999px;
            padding: 14px 22px;
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(16, 185, 129, 0.18);
            transition: all 0.18s ease;
        }

        .btn-change-plan:hover {
            background: #ecfdf5;
            color: #065f46;
            border-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.24);
        }

        .btn-change-plan:active {
            transform: translateY(1px) scale(0.98);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.18);
        }

        .btn-change-plan i {
            font-size: 20px;
        }

        .change-plan-hint {
            margin: 0;
            color: #047857;
            font-size: 14px;
            line-height: 1.4;
        }

        @media (max-width: 640px) {
            .btn-change-plan {
                width: 100%;
                justify-content: center;
            }

            .change-plan-box {
                width: 100%;
            }
        }
    </style>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

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

                @if ($showInstallmentPlanner && $paymentPlan && empty($forcePlanSelection))
                    <div class="mt-5 rounded-3xl border p-5" style="border-color:rgba(255,255,255,0.22);background:rgba(255,255,255,0.94);box-shadow:0 18px 36px rgba(12,48,34,0.12);">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-emerald-700">{{ $paymentPlan->plan_label }}</p>
                                <h3 class="text-2xl font-extrabold tracking-tight text-zinc-900">Ringkasan Ansuran</h3>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background:#dcfce7;color:#047857;">
                                {{ ucfirst((string) $paymentPlan->status) }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border px-4 py-3 text-sm" style="border-color:#d5e7dc;background:#f8fffb;">
                                <p class="text-xs uppercase tracking-wide text-zinc-500">Jumlah Yuran</p>
                                <p class="mt-1 text-lg font-bold text-zinc-900">RM {{ number_format((float) $paymentPlan->total_amount, 2) }}</p>
                            </div>
                            <div class="rounded-2xl border px-4 py-3 text-sm" style="border-color:#d5e7dc;background:#f8fffb;">
                                <p class="text-xs uppercase tracking-wide text-zinc-500">Jumlah Dibayar</p>
                                <p class="mt-1 text-lg font-bold text-emerald-700">RM {{ number_format((float) $paymentPlan->paid_amount, 2) }}</p>
                            </div>
                            <div class="rounded-2xl border px-4 py-3 text-sm" style="border-color:#d5e7dc;background:#f8fffb;">
                                <p class="text-xs uppercase tracking-wide text-zinc-500">Baki Bayaran</p>
                                <p class="mt-1 text-lg font-bold text-amber-700">RM {{ number_format((float) $paymentPlan->balance_amount, 2) }}</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="mb-2 flex items-center justify-between text-xs font-semibold text-zinc-600">
                                <span>Status Bayaran</span>
                                <span>{{ $paymentPlanProgress }}%</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-emerald-100">
                                <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $paymentPlanProgress }}%;"></div>
                            </div>
                        </div>

                        @if ($showGatewayMethods)
                            <div class="mt-4 rounded-2xl border px-4 py-4" style="border-color:#d5e7dc;background:#f4fbf7;">
                                <div class="mb-3 flex flex-wrap gap-2 text-sm font-semibold text-zinc-900">
                                    @if ($paymentGatewaySetting->enable_fpx)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-100 px-3 py-1.5" style="background:#ffffff;color:#166a4c;">
                                            <i class="ph ph-bank text-base"></i>
                                            FPX / Internet Banking
                                        </span>
                                    @endif

                                    @if ($paymentGatewaySetting->enable_duitnow_qr)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-100 px-3 py-1.5" style="background:#ffffff;color:#166a4c;">
                                            <i class="ph ph-qr-code text-base"></i>
                                            DuitNow QR
                                        </span>
                                    @endif
                                </div>

                                <p class="text-sm leading-relaxed text-zinc-700">{{ $paymentGatewayNotice }}</p>
                                <p class="mt-2 text-sm leading-relaxed text-emerald-800">
                                    Anda masih boleh memilih atau menukar kaedah pembayaran di halaman ToyyibPay sebelum bayaran disahkan.
                                </p>
                            </div>
                        @endif

                        <div class="change-plan-box">
                            @if (! empty($canChangePaymentPlan))
                                <button
                                    type="button"
                                    data-open-change-plan-modal
                                    class="btn-change-plan"
                                >
                                    <i class="ph ph-arrows-clockwise"></i>
                                    <span>Tukar Pilihan Bayaran</span>
                                </button>
                                <p class="change-plan-hint">
                                    Belum buat bayaran? Anda boleh kembali dan pilih kaedah bayaran lain.
                                </p>
                            @else
                                <p class="text-sm font-medium text-zinc-600">
                                    Pilihan bayaran tidak boleh ditukar kerana bayaran telah dibuat.
                                </p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex-1 rounded-3xl border border-zinc-200 bg-white p-6 shadow-lg">
                <h3 class="text-3xl font-extrabold tracking-tight text-zinc-800">Maklumat Pembayaran</h3>
                <p class="mt-2 text-sm text-zinc-600">{{ $paymentGatewayNotice }}</p>
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

                @if ($errors->has('payment_gateway') || $errors->has('payment_plan'))
                    <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ $errors->first('payment_gateway') ?: $errors->first('payment_plan') }}
                    </div>
                @endif

                @if ($showPlanSelector && ! empty($availablePaymentOptionLabels))
                    <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        <p class="font-semibold">Pilihan bayaran tersedia untuk keluarga anda:</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($availablePaymentOptionLabels as $paymentOptionLabel)
                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">
                                    {{ $paymentOptionLabel }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($showPlanSelector)
                    <div class="mt-5 space-y-4">
                        <div>
                            <h4 class="text-lg font-bold text-zinc-900">Pilih Pelan Bayaran</h4>
                            <p class="mt-1 text-sm text-zinc-600">Pilih cara bayaran yang sesuai untuk keluarga anda.</p>
                        </div>

                        @if ($availablePlans === [])
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                                Tiada pilihan bayaran tersedia untuk keluarga anda dalam kempen semasa. Sila hubungi pihak PIBG atau pentadbir portal.
                            </div>
                        @else
                            <div class="grid gap-3">
                                @foreach ($availablePlans as $planOption)
                                    <form method="POST" action="{{ route('parent.payments.plan.select', $familyBilling) }}" class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 transition hover:border-emerald-300 hover:bg-emerald-50">
                                        @csrf
                                        <input type="hidden" name="plan_type" value="{{ $planOption['type'] }}">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-base font-bold text-zinc-900">{{ $planOption['label'] }}</p>
                                                <p class="mt-1 text-sm text-zinc-600">
                                                    @foreach ($planOption['amounts'] as $amountIndex => $amount)
                                                        {{ $amountIndex > 0 ? ' + ' : '' }}RM{{ number_format((float) $amount, 2) }}
                                                    @endforeach
                                                </p>
                                            </div>
                                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                                Pilih {{ $planOption['label'] }}
                                            </button>
                                        </div>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @elseif ($showInstallmentPlanner && $paymentPlan)
                    <div class="mt-5 space-y-4">
                        <div>
                            <h4 class="text-lg font-bold text-zinc-900">Maklumat Pembayar</h4>
                            <p class="mt-1 text-sm text-zinc-600">Isi maklumat di bawah, kemudian pilih ansuran untuk diteruskan ke halaman ToyyibPay.</p>
                        </div>

                        <form method="POST" class="space-y-4">
                            @csrf
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Nama Ibu Bapa / Penjaga</label>
                                <input
                                    name="payer_name"
                                    type="text"
                                    required
                                    class="w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm"
                                    placeholder="Contoh: Nurul Aini binti Ahmad"
                                    value="{{ old('payer_name', $defaultName) }}"
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
                                    value="{{ old('payer_email', $defaultEmail) }}"
                                >
                                <x-auth-session-status class="text-xs text-red-600" :status="$errors->first('payer_email')" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">No Telefon</label>
                                <input name="payer_phone" type="text" required class="w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm" value="{{ old('payer_phone', $defaultPhone) }}">
                                <x-auth-session-status class="text-xs text-red-600" :status="$errors->first('payer_phone')" />
                            </div>

                            <div class="space-y-3">
                                <h4 class="text-sm font-bold uppercase tracking-wide text-zinc-500">Ansuran</h4>
                                @foreach ($paymentPlan->installments as $installment)
                                    @php
                                        $isPaidInstallment = (string) $installment->status === \App\Models\FamilyPaymentInstallment::STATUS_PAID;
                                        $donationChoice = (string) old("installment_donation_choice.{$installment->id}", '0');
                                        $donationCustom = (string) old("installment_donation_custom.{$installment->id}", '');
                                        $resolvedDonationAmount = $donationChoice === 'other'
                                            ? (is_numeric($donationCustom) ? (float) $donationCustom : 0.0)
                                            : (is_numeric($donationChoice) ? (float) $donationChoice : 0.0);
                                        $installmentAmount = (float) $installment->amount;
                                        $installmentTotalPreview = $installmentAmount + max(0, $resolvedDonationAmount);
                                    @endphp
                                    <div class="rounded-2xl border border-zinc-200 bg-white px-4 py-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-base font-bold text-zinc-900">Ansuran {{ $installment->installment_no }}</p>
                                                <p class="mt-1 text-sm text-zinc-600">RM {{ number_format((float) $installment->amount, 2) }}</p>
                                                <p class="mt-1 text-xs font-semibold {{ $isPaidInstallment ? 'text-emerald-700' : 'text-amber-700' }}">{{ $installment->status_label }}</p>
                                            </div>
                                            @if ($isPaidInstallment)
                                                <span class="inline-flex items-center rounded-xl bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-700">Selesai Dibayar</span>
                                            @else
                                                <div class="w-full space-y-4 sm:max-w-md sm:text-right">
                                                    <div
                                                        class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 text-left"
                                                        data-installment-donation-card
                                                        data-base-amount="{{ number_format($installmentAmount, 2, '.', '') }}"
                                                    >
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div>
                                                                <p class="text-sm font-bold text-zinc-900">Sumbangan Tambahan PIBG</p>
                                                                <p class="mt-1 text-xs leading-relaxed text-zinc-600">
                                                                    Sumbangan tambahan adalah pilihan. Jumlah ini akan direkodkan berasingan daripada yuran.
                                                                </p>
                                                            </div>
                                                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-emerald-700 shadow-sm">
                                                                Pilihan
                                                            </span>
                                                        </div>

                                                        <div class="mt-3 flex flex-wrap gap-2">
                                                            @foreach ([0, 10, 20, 50] as $presetAmount)
                                                                @php
                                                                    $presetValue = (string) $presetAmount;
                                                                    $isSelectedPreset = $donationChoice === $presetValue;
                                                                @endphp
                                                                <label class="cursor-pointer">
                                                                    <input
                                                                        type="radio"
                                                                        name="installment_donation_choice[{{ $installment->id }}]"
                                                                        value="{{ $presetValue }}"
                                                                        class="sr-only"
                                                                        data-installment-donation-choice
                                                                        @checked($isSelectedPreset)
                                                                    >
                                                                    <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $isSelectedPreset ? 'border-emerald-600 bg-emerald-600 text-white shadow-sm' : 'border-emerald-200 bg-white text-emerald-800 hover:border-emerald-400 hover:bg-emerald-50' }}">
                                                                        RM{{ number_format((float) $presetAmount, 0) }}
                                                                    </span>
                                                                </label>
                                                            @endforeach

                                                            <label class="cursor-pointer">
                                                                <input
                                                                    type="radio"
                                                                    name="installment_donation_choice[{{ $installment->id }}]"
                                                                    value="other"
                                                                    class="sr-only"
                                                                    data-installment-donation-choice
                                                                    @checked($donationChoice === 'other')
                                                                >
                                                                <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $donationChoice === 'other' ? 'border-emerald-600 bg-emerald-600 text-white shadow-sm' : 'border-emerald-200 bg-white text-emerald-800 hover:border-emerald-400 hover:bg-emerald-50' }}">
                                                                    Lain-lain
                                                                </span>
                                                            </label>
                                                        </div>

                                                        <div class="{{ $donationChoice === 'other' ? '' : 'hidden' }} mt-3" data-installment-custom-wrap>
                                                            <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Masukkan jumlah sumbangan</label>
                                                            <div class="mt-1 flex overflow-hidden rounded-xl border border-emerald-200 bg-white">
                                                                <span class="inline-flex items-center border-r border-emerald-200 px-3 py-2 text-sm font-semibold text-zinc-700">RM</span>
                                                                <input
                                                                    type="number"
                                                                    name="installment_donation_custom[{{ $installment->id }}]"
                                                                    min="1"
                                                                    max="1000"
                                                                    step="0.01"
                                                                    value="{{ $donationCustom }}"
                                                                    class="w-full border-0 px-3 py-2 text-sm text-zinc-800 focus:ring-0"
                                                                    placeholder="Minimum RM1"
                                                                    data-installment-custom-input
                                                                >
                                                            </div>
                                                        </div>

                                                        <x-auth-session-status class="mt-2 text-xs text-red-600" :status="$errors->first('installment_donation_choice.'.$installment->id)" />
                                                        <x-auth-session-status class="mt-1 text-xs text-red-600" :status="$errors->first('installment_donation_custom.'.$installment->id)" />

                                                        <div class="mt-3 rounded-2xl border border-emerald-100 bg-white px-3 py-3 text-sm">
                                                            <div class="flex items-center justify-between gap-3">
                                                                <span class="font-semibold text-zinc-700">Jumlah bayaran</span>
                                                                <strong class="text-base font-extrabold text-emerald-700" data-installment-total-label>
                                                                    RM {{ number_format($installmentTotalPreview, 2) }}
                                                                </strong>
                                                            </div>
                                                            <p class="mt-1 text-xs text-zinc-600">
                                                                Yuran: RM {{ number_format($installmentAmount, 2) }}
                                                                <span data-installment-breakdown>
                                                                    @if ($resolvedDonationAmount > 0)
                                                                        + Sumbangan: RM {{ number_format($resolvedDonationAmount, 2) }}
                                                                    @else
                                                                        + Sumbangan: RM 0.00
                                                                    @endif
                                                                </span>
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div class="sm:text-right">
                                                        <button
                                                            type="submit"
                                                            formaction="{{ route('parent.payments.installments.pay', $installment) }}"
                                                            class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                                                        >
                                                            Bayar Sekarang
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </form>
                    </div>
                @else
                    <form action="{{ route('parent.payments.create', $familyBilling) }}" method="POST" class="mt-4 space-y-4">
                        @csrf
                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Nama Ibu Bapa / Penjaga</label>
                            <input
                                name="payer_name"
                                type="text"
                                required
                                class="w-full rounded-lg border border-zinc-300 px-4 py-3 text-sm"
                                placeholder="Contoh: Nurul Aini binti Ahmad"
                                value="{{ old('payer_name', $defaultName) }}"
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
                                value="{{ old('payer_email', $defaultEmail) }}"
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
                @endif
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
                    @php
                        $attemptStatus = strtolower((string) $attempt->status);
                        $statusLabel = ucfirst((string) $attempt->status);
                        $statusBadgeClass = 'bg-zinc-100 text-zinc-600';

                        if ($attemptStatus === 'success') {
                            $statusLabel = 'Success';
                            $statusBadgeClass = 'bg-emerald-100 text-emerald-700';
                        } elseif ($attemptStatus === 'pending') {
                            $statusLabel = 'Pending';
                            $statusBadgeClass = 'bg-amber-100 text-amber-700';
                        } elseif (in_array($attemptStatus, ['failed', 'cancelled', 'canceled'], true)) {
                            $statusLabel = $attemptStatus === 'failed' ? 'Failed' : 'Cancelled';
                            $statusBadgeClass = 'bg-rose-100 text-rose-700';
                        }
                    @endphp
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-700">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold text-zinc-900">{{ $attempt->external_order_display }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[0.65rem] font-semibold {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
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
                                Ã‚Â· Sumbangan RM {{ number_format((float) $payment['donation_amount'], 2) }}
                            @endif
                        </p>
                    </div>
                @empty
                    <p class="text-xs text-zinc-500">Tiada rekod bayaran berjaya untuk tahun {{ $lastYear }}.</p>
                @endforelse
            </div>
        </section>
    </div>

    @if ($showInstallmentPlanner && $paymentPlan && empty($forcePlanSelection) && ! empty($canChangePaymentPlan))
        <div
            data-change-plan-modal
            class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-950/50 px-4"
            aria-hidden="true"
        >
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 rounded-full bg-emerald-100 p-2 text-emerald-700">
                        <i class="ph ph-pencil-simple text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-extrabold text-zinc-900">Tukar pilihan bayaran?</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600">
                            Pilihan bayaran semasa akan dibatalkan dan anda boleh memilih semula Bayaran Penuh atau Ansuran. Teruskan?
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button
                        type="button"
                        data-close-change-plan-modal
                        class="inline-flex items-center justify-center rounded-full border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50"
                    >
                        Batal
                    </button>

                    <form method="POST" action="{{ route('payment-plan.change', $paymentPlan) }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                        >
                            Ya, Tukar Pilihan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

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

                if (!presetInput || !customInput || !donationLabel || !totalLabel || !totalAmountInput) {
                    return;
                }

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

            (() => {
                const modal = document.querySelector('[data-change-plan-modal]');
                const openButton = document.querySelector('[data-open-change-plan-modal]');
                const closeButtons = document.querySelectorAll('[data-close-change-plan-modal]');

                if (!modal || !openButton) {
                    return;
                }

                const openModal = () => {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.setAttribute('aria-hidden', 'false');
                };

                const closeModal = () => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    modal.setAttribute('aria-hidden', 'true');
                };

                openButton.addEventListener('click', openModal);
                closeButtons.forEach((button) => button.addEventListener('click', closeModal));
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeModal();
                    }
                });
            })();

            (() => {
                const cards = document.querySelectorAll('[data-installment-donation-card]');

                if (!cards.length) {
                    return;
                }

                const toMoney = (value) => `RM ${Number(value).toFixed(2)}`;

                cards.forEach((card) => {
                    const baseAmount = Number(card.dataset.baseAmount || '0');
                    const choiceInputs = card.querySelectorAll('[data-installment-donation-choice]');
                    const customWrap = card.querySelector('[data-installment-custom-wrap]');
                    const customInput = card.querySelector('[data-installment-custom-input]');
                    const totalLabel = card.querySelector('[data-installment-total-label]');
                    const breakdownLabel = card.querySelector('[data-installment-breakdown]');

                    if (!choiceInputs.length || !customWrap || !customInput || !totalLabel || !breakdownLabel) {
                        return;
                    }

                    const refreshChipState = () => {
                        choiceInputs.forEach((input) => {
                            const chip = input.nextElementSibling;
                            if (!(chip instanceof HTMLElement)) {
                                return;
                            }

                            chip.classList.toggle('border-emerald-600', input.checked);
                            chip.classList.toggle('bg-emerald-600', input.checked);
                            chip.classList.toggle('text-white', input.checked);
                            chip.classList.toggle('shadow-sm', input.checked);
                            chip.classList.toggle('border-emerald-200', !input.checked);
                            chip.classList.toggle('bg-white', !input.checked);
                            chip.classList.toggle('text-emerald-800', !input.checked);
                        });
                    };

                    const selectedChoice = () => {
                        const checked = Array.from(choiceInputs).find((input) => input.checked);

                        return checked ? checked.value : '0';
                    };

                    const donationAmount = () => {
                        if (selectedChoice() === 'other') {
                            const customValue = Number((customInput.value || '').trim());

                            return Number.isFinite(customValue) && customValue > 0 ? customValue : 0;
                        }

                        const presetValue = Number(selectedChoice());

                        return Number.isFinite(presetValue) && presetValue > 0 ? presetValue : 0;
                    };

                    const refresh = () => {
                        const choice = selectedChoice();
                        const donation = donationAmount();
                        const total = baseAmount + donation;

                        customWrap.classList.toggle('hidden', choice !== 'other');
                        totalLabel.textContent = toMoney(total);
                        breakdownLabel.textContent = `+ Sumbangan: ${toMoney(donation)}`;
                        refreshChipState();
                    };

                    choiceInputs.forEach((input) => {
                        input.addEventListener('change', refresh);
                    });

                    customInput.addEventListener('input', refresh);

                    refresh();
                });
            })();
        </script>
    @endpush
</x-layouts::app>
