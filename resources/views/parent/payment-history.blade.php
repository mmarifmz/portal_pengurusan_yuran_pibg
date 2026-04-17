<x-layouts::app :title="__('Sejarah Pembayaran')">
    <div class="space-y-6">
        <div class="box p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-[color:var(--brand-forest)]">Sejarah Transaksi Pembayaran</h2>
                    <p class="mt-1 text-sm text-zinc-600">Pilih transaksi untuk lihat ringkasan atau buat bayaran baru.</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <a
                    href="{{ route('parent.payments.history', ['filter' => 'all']) }}"
                    class="rounded-full border px-4 py-1.5 text-xs font-semibold transition {{ $activeFilter === 'all' ? 'border-emerald-400 bg-emerald-50 text-emerald-700' : 'border-zinc-300 bg-white text-zinc-600 hover:bg-zinc-50' }}"
                >
                    Semua
                </a>
                <a
                    href="{{ route('parent.payments.history', ['filter' => 'successful']) }}"
                    class="rounded-full border px-4 py-1.5 text-xs font-semibold transition {{ $activeFilter === 'successful' ? 'border-emerald-400 bg-emerald-50 text-emerald-700' : 'border-zinc-300 bg-white text-zinc-600 hover:bg-zinc-50' }}"
                >
                    Successful Payment
                </a>
                <a
                    href="{{ route('parent.payments.history', ['filter' => 'pending']) }}"
                    class="rounded-full border px-4 py-1.5 text-xs font-semibold transition {{ $activeFilter === 'pending' ? 'border-sky-400 bg-sky-50 text-sky-700' : 'border-zinc-300 bg-white text-zinc-600 hover:bg-zinc-50' }}"
                >
                    Pending Completion
                </a>
            </div>
        </div>

        @if ($errors->has('payment_gateway'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('payment_gateway') }}
            </div>
        @endif

        <div class="box overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <th class="px-4 py-3">Tarikh</th>
                            <th class="px-4 py-3">Order ID</th>
                            <th class="px-4 py-3">Bill Code</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3 text-right">Jumlah (RM)</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Return Status</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 text-sm text-zinc-800">
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td class="px-4 py-3">{{ $transaction->created_at_for_display?->format('d M Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $transaction->external_order_display }}</td>
                                <td class="px-4 py-3">{{ $transaction->provider_bill_code ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $transaction->payer_name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $transaction->amount, 2) }}</td>
                                <td class="px-4 py-3">
                                    {{ $transaction->status === 'superseded' ? 'Dibatalkan' : ucfirst($transaction->status) }}
                                </td>
                                <td class="px-4 py-3">{{ $transaction->return_status ? ucfirst($transaction->return_status) : 'Pending completion' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('parent.payments.summary', $transaction->external_order_id) }}" class="rounded-lg border border-zinc-300 px-3 py-1 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">
                                            Ringkasan
                                        </a>

                                        @if ($transaction->familyBilling)
                                            <a href="{{ route('parent.payments.checkout', ['familyBilling' => $transaction->familyBilling, 'from_transaction' => $transaction->id]) }}" class="rounded-lg border border-emerald-300 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                                Bayaran Baru
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-zinc-500">Tiada transaksi pembayaran direkodkan lagi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-200 px-4 py-3">
                {{ $transactions->appends(['filter' => $activeFilter])->links() }}
            </div>
        </div>
    </div>
</x-layouts::app>
