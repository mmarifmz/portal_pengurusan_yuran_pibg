<x-layouts::app :title="__('Ringkasan Pembayaran')">
    <div class="space-y-6">
        <div class="box p-6">
            <h2 class="text-lg font-semibold text-[color:var(--brand-forest)]">Status Transaksi</h2>
            <p class="mt-1 text-sm text-zinc-600">Kod keluarga {{ $transaction->familyBilling->family_code }} · {{ $transaction->familyBilling->billing_year }}</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-zinc-200 bg-[color:var(--brand-soft)] px-4 py-3 text-sm">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Jumlah</p>
                    <p class="text-xl font-bold text-[color:var(--brand-green)]">RM {{ number_format($transaction->amount, 2) }}</p>
                </div>
                <div class="rounded-2xl border border-zinc-200 bg-[color:var(--brand-soft)] px-4 py-3 text-sm">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Status</p>
                    <p class="text-xl font-bold text-zinc-700">{{ ucfirst($transaction->status) }}</p>
                </div>
                <div class="rounded-2xl border border-zinc-200 bg-[color:var(--brand-soft)] px-4 py-3 text-sm">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Bayar Pada</p>
                    <p class="text-xl font-bold text-zinc-700">{{ $transaction->paid_at?->format('d M Y H:i') ?? '–' }}</p>
                </div>
            </div>

            <div class="mt-5 space-y-2 text-sm text-zinc-600">
                <p>Order ID: {{ $transaction->external_order_id }}</p>
                <p>Bill Code: {{ $transaction->provider_bill_code }}</p>
                <p>Invoice: {{ $transaction->provider_invoice_no ?? 'Belum dijana' }}</p>
            </div>

            <div class="mt-5 flex gap-2">
                <a href="{{ route('parent.payments.receipt', $transaction->external_order_id) }}" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">Muat Turun Resit</a>
                <a href="{{ route('parent.dashboard') }}" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-[color:var(--brand-forest)] hover:bg-zinc-50">Kembali ke Dashboard</a>
            </div>
        </div>

        <div class="box p-6">
            <h3 class="text-base font-semibold text-[color:var(--brand-forest)]">Maklumat Pembayar</h3>
            <div class="mt-3 grid gap-1 text-sm text-zinc-600">
                <p>Email: {{ $transaction->payer_email ?? '–' }}</p>
                <p>Telefon: {{ $transaction->payer_phone ?? '–' }}</p>
            </div>
        </div>

        <div class="box p-6">
            <h3 class="text-base font-semibold text-[color:var(--brand-forest)]">Senarai Anak</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach($familyChildren as $child)
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm">
                        <p class="font-semibold text-zinc-900">{{ $child->full_name }}</p>
                        <p class="text-xs text-zinc-500">{{ $child->class_name }}</p>
                        <p class="text-[0.65rem] text-zinc-500">No. Murid: {{ $child->student_no }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts::app>
