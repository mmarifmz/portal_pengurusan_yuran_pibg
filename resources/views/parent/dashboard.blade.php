<x-layouts::app :title="__('Parent Dashboard')">
    @php
        $nextOutstandingBilling = $familyBillings->firstWhere(fn ($billing) => $billing->outstanding_amount > 0);
        $sumbanganBilling = $familyBillings->firstWhere(fn ($billing) => $billing->outstanding_amount <= 0)
            ?? $nextOutstandingBilling
            ?? $familyBillings->first();
    @endphp

    <style>
        :root {
            --portal-forest: #174a34;
            --portal-green: #2f7a55;
            --portal-gold: #e5b338;
            --portal-ink: #1f2a24;
            --portal-soft: #f3f8f3;
        }

        .portal-card {
            border: 1px solid #e5e7eb;
            background: rgba(255, 255, 255, 0.94);
            border-radius: 1rem;
            box-shadow: 0 8px 24px rgba(30, 41, 59, 0.07);
        }

        .portal-hero {
            background: linear-gradient(135deg, rgba(43, 125, 86, 0.14), rgba(229, 179, 56, 0.16));
        }

        .portal-heading {
            color: var(--portal-forest);
        }

        .portal-kicker {
            color: var(--portal-forest);
            background: rgba(255, 255, 255, 0.8);
            box-shadow: inset 0 0 0 1px rgba(229, 179, 56, 0.45);
        }

        .portal-primary-btn {
            background: var(--portal-green);
            color: #fff;
            box-shadow: 0 12px 24px rgba(47, 122, 85, 0.18);
        }

        .portal-primary-btn:hover {
            background: var(--portal-forest);
        }

        .portal-pay-btn {
            position: relative;
            overflow: hidden;
            border: 1px solid #b45309;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #ffffff;
            box-shadow: 0 14px 28px rgba(217, 119, 6, 0.32);
            isolation: isolate;
        }

        .portal-pay-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(115deg, transparent 34%, rgba(255, 255, 255, 0.32) 48%, transparent 66%);
            transform: translateX(-130%);
            transition: transform 0.75s ease;
            z-index: 0;
        }

        .portal-pay-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 32px rgba(217, 119, 6, 0.38);
        }

        .portal-pay-btn:hover::before {
            transform: translateX(130%);
        }

        .portal-pay-btn > span {
            position: relative;
            z-index: 1;
        }

        .portal-outline-btn {
            background: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .portal-outline-btn:hover {
            background: #f9fafb;
        }

        .portal-donation-btn {
            position: relative;
            overflow: hidden;
            border: 1px solid #0f766e;
            background: linear-gradient(135deg, #0f766e, #0ea5a4);
            color: #ffffff;
            box-shadow: 0 14px 26px rgba(15, 118, 110, 0.28);
            isolation: isolate;
        }

        .portal-donation-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, 0.3) 48%, transparent 68%);
            transform: translateX(-130%);
            transition: transform 0.75s ease;
            z-index: 0;
        }

        .portal-donation-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 30px rgba(15, 118, 110, 0.34);
        }

        .portal-donation-btn:hover::before {
            transform: translateX(130%);
        }

        .portal-donation-btn > span {
            position: relative;
            z-index: 1;
        }

        .portal-donation-btn svg {
            position: relative;
            z-index: 1;
            transition: transform 0.25s ease;
        }

        .portal-donation-btn:hover svg {
            transform: scale(1.08);
        }

        .portal-positive {
            color: #047857;
        }

        .portal-negative {
            color: #e11d48;
        }

        .portal-table-title {
            color: var(--portal-forest);
        }
    </style>

    <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="portal-card portal-hero p-6 sm:p-8">
            <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr] lg:items-end">
                <div>
                    <p class="portal-kicker inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] sm:text-xs">
                        Parent Dashboard
                    </p>
                    <h1 class="portal-heading mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl">
                        Keluarga Anda Dalam
                        <span style="color: var(--portal-green);">Portal PIBG</span>
                    </h1>
                    <p class="mt-3 max-w-3xl text-sm leading-relaxed text-zinc-700 sm:text-base">
                        Semak jumlah anak yang dipautkan, status bil keluarga, dan teruskan bayaran jika masih ada baki tertunggak.
                    </p>

                    @if (! empty($isTesterMode))
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                            Mod Tester Treasury aktif. Semua transaksi bayaran akan dihantar sebagai RM1.00.
                        </div>
                    @endif
                </div>

                <div class="portal-card bg-white/90 p-5">
                    <h2 class="portal-heading text-base font-bold sm:text-lg">Tindakan Cepat</h2>
                    <div class="mt-4 grid gap-3">
                        @if ($nextOutstandingBilling)
                            <a
                                href="{{ route('parent.payments.checkout', $nextOutstandingBilling) }}"
                                class="portal-pay-btn inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold transition"
                            >
                                <span>Bayar Yuran PIBG {{ $billingYear }}</span>
                            </a>
                        @else
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                @if (! empty($hasAdditionalDonationForLatestPaidYear))
                                    Tahniah ! Anda telah membayar yuran PIBG serta memberi sumbangan tambahan bagi tahun {{ $latestPaidYear ?? $billingYear }}.
                                @else
                                    Tahniah ! Anda telah membayar yuran PIBG bagi tahun {{ $latestPaidYear ?? $billingYear }}.
                                @endif
                            </div>
                        @endif

                        @if ($sumbanganBilling)
                            <a
                                href="{{ route('parent.payments.checkout', $sumbanganBilling) }}"
                                class="portal-donation-btn inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M10 2a1 1 0 0 1 1 1v1.07a5.002 5.002 0 0 1 0 9.86V15a1 1 0 1 1-2 0v-1.07a5.002 5.002 0 0 1 0-9.86V3a1 1 0 0 1 1-1Zm-3 7a3 3 0 1 0 3-3 3 3 0 0 0-3 3Z" />
                                    <path d="M4 10a1 1 0 0 1 1 1 5 5 0 0 0 10 0 1 1 0 1 1 2 0 7 7 0 0 1-14 0 1 1 0 0 1 1-1Z" />
                                </svg>
                                <span>Sumbangan Tambahan</span>
                            </a>
                        @else
                            <span class="portal-outline-btn inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold opacity-60">
                                Sumbangan Tambahan
                            </span>
                        @endif
                        <a
                            href="{{ route('parent.payments.history') }}"
                            class="portal-outline-btn inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold transition"
                        >
                            Sejarah Pembayaran
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-3">
            <article class="portal-card p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500">Total Children Linked</p>
                <p class="portal-heading mt-3 text-4xl font-extrabold">{{ $children->count() }}</p>
                <p class="mt-2 text-sm text-zinc-600">Bilangan anak yang dipautkan pada nombor parent ini.</p>
            </article>

            <article class="portal-card p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500">Family Billings ({{ $billingYear }})</p>
                <p class="portal-heading mt-3 text-4xl font-extrabold">{{ $familyBillings->count() }}</p>
                <p class="mt-2 text-sm text-zinc-600">Jumlah bil keluarga yang tersedia untuk tahun semasa.</p>
            </article>

            <article class="portal-card p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500">Outstanding Family Total (RM)</p>
                <p class="mt-3 text-4xl font-extrabold {{ $totalOutstanding > 0 ? 'portal-negative' : 'portal-positive' }}">
                    {{ number_format($totalOutstanding, 2) }}
                </p>
                <p class="mt-2 text-sm text-zinc-600">
                    {{ $totalOutstanding > 0 ? 'Baki tertunggak yang masih perlu dijelaskan.' : 'Tiada baki tertunggak untuk keluarga anda.' }}
                </p>
            </article>
        </section>

        <section class="portal-card overflow-hidden">
            <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-4">
                <h2 class="portal-table-title text-lg font-bold">Status Bil Keluarga</h2>
                <p class="mt-1 text-sm text-zinc-600">Ringkasan bayaran mengikut kod keluarga.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-white">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <th class="px-6 py-4">Family Code</th>
                            <th class="px-6 py-4 text-right">Fee (RM)</th>
                            <th class="px-6 py-4 text-right">Paid (RM)</th>
                            <th class="px-6 py-4 text-right">Outstanding (RM)</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm text-zinc-800">
                        @forelse ($familyBillings as $billing)
                            <tr>
                                <td class="portal-heading px-6 py-5 font-semibold">{{ $billing->family_code }}</td>
                                <td class="px-6 py-5 text-right">{{ number_format($billing->fee_amount, 2) }}</td>
                                <td class="px-6 py-5 text-right">{{ number_format($billing->paid_amount, 2) }}</td>
                                <td class="px-6 py-5 text-right font-semibold {{ $billing->outstanding_amount > 0 ? 'portal-negative' : 'portal-positive' }}">
                                    {{ number_format($billing->outstanding_amount, 2) }}
                                </td>
                                <td class="px-6 py-5">
                                    @if ($billing->outstanding_amount > 0)
                                        <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">
                                            {{ ucfirst($billing->status) }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            {{ ucfirst($billing->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-5 text-right">
                                    @if ($billing->outstanding_amount > 0)
                                        <a
                                            href="{{ route('parent.payments.checkout', $billing) }}"
                                            class="portal-primary-btn inline-flex items-center rounded-xl px-4 py-2 text-xs font-semibold transition"
                                        >
                                            Bayar
                                        </a>
                                    @else
                                        <span class="text-xs font-medium text-zinc-500">Lengkap</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-zinc-500">
                                    No family billing found yet. Please contact school admin.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="portal-card overflow-hidden">
            <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-4">
                <h2 class="portal-table-title text-lg font-bold">Rekod Anak Dipautkan</h2>
                <p class="mt-1 text-sm text-zinc-600">Senarai anak yang berkaitan dengan nombor parent semasa.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-white">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <th class="px-6 py-4">Student No</th>
                            <th class="px-6 py-4">Family Code</th>
                            <th class="px-6 py-4">Name</th>
                            <th class="px-6 py-4">Class</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm text-zinc-800">
                        @forelse ($children as $student)
                            <tr>
                                <td class="portal-heading px-6 py-5 font-semibold">{{ $student->student_no }}</td>
                                <td class="px-6 py-5">{{ $student->family_code ?? '-' }}</td>
                                <td class="px-6 py-5 font-medium">{{ $student->full_name }}</td>
                                <td class="px-6 py-5">{{ $student->class_name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-zinc-500">No children linked to your phone yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="portal-card overflow-hidden">
            <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-4">
                <h2 class="portal-table-title text-lg font-bold">Sejarah Bayaran Tahun Lepas (Imported)</h2>
                <p class="mt-1 text-sm text-zinc-600">Rujukan bayaran berstatus paid dari portal tahun lepas.</p>
                <p class="mt-2 text-xs font-semibold text-zinc-700">
                    Total paid: RM {{ number_format($legacyPaidTotal ?? 0, 2) }}
                    <span class="mx-2">|</span>
                    Total sumbangan: RM {{ number_format($legacyDonationTotal ?? 0, 2) }}
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-white">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <th class="px-6 py-4">Tarikh Bayar</th>
                            <th class="px-6 py-4">Rujukan</th>
                            <th class="px-6 py-4">Anak</th>
                            <th class="px-6 py-4">Kelas</th>
                            <th class="px-6 py-4 text-right">Jumlah (RM)</th>
                            <th class="px-6 py-4 text-right">Sumbangan (RM)</th>
                            <th class="px-6 py-4">Tahun</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm text-zinc-800">
                        @forelse (($legacyPayments ?? collect()) as $legacyPayment)
                            <tr>
                                <td class="px-6 py-5">{{ $legacyPayment->paid_at?->format('d M Y H:i') ?: '-' }}</td>
                                <td class="px-6 py-5 font-mono text-xs">{{ $legacyPayment->payment_reference ?: '-' }}</td>
                                <td class="px-6 py-5 font-medium">{{ $legacyPayment->student_name }}</td>
                                <td class="px-6 py-5">{{ $legacyPayment->class_name ?: '-' }}</td>
                                <td class="px-6 py-5 text-right">{{ number_format((float) $legacyPayment->amount_paid, 2) }}</td>
                                <td class="px-6 py-5 text-right">{{ number_format((float) $legacyPayment->donation_amount, 2) }}</td>
                                <td class="px-6 py-5">{{ $legacyPayment->source_year }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-zinc-500">Tiada data bayaran tahun lepas yang diimport lagi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
