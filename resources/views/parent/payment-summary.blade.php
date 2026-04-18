<x-layouts::app :title="__('Ringkasan Pembayaran')">
    @php
        $status = (string) ($transaction->status ?? 'pending');
        $stamp = match ($status) {
            'success' => [
                'label' => 'PAID',
                'classes' => 'border-emerald-600 bg-emerald-600 text-white',
                'note' => 'Pembayaran berjaya diterima oleh portal.',
            ],
            'failed' => [
                'label' => 'FAILED',
                'classes' => 'border-rose-600 bg-rose-600 text-white',
                'note' => 'Pembayaran tidak berjaya. Sila cuba semula.',
            ],
            'superseded' => [
                'label' => 'DIBATALKAN',
                'classes' => 'border-zinc-500 bg-zinc-500 text-white',
                'note' => 'Bil ini sudah dinyahaktifkan kerana terdapat bil baharu.',
            ],
            default => [
                'label' => 'PENDING',
                'classes' => 'border-amber-300 bg-amber-100 text-amber-800',
                'note' => 'Portal sedang menunggu pengesahan pembayaran.',
            ],
        };

        $hasReturn = filled($transaction->raw_return);
        $hasCallback = filled($transaction->raw_callback);
        $gatewaySeen = $hasReturn || $hasCallback;
        $statusDisplay = $status === 'superseded' ? 'Dibatalkan' : ucfirst($status);
    @endphp

    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm;
            }

            .no-print {
                display: none !important;
            }

            .print-optional {
                display: none !important;
            }

            .print-sheet {
                font-size: 11px;
                line-height: 1.3;
            }

            .print-single-page {
                max-height: 278mm;
                overflow: hidden;
            }

            .print-sheet .box {
                border: 1px solid #d4d4d8 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
                break-inside: avoid;
                padding: 0.65rem !important;
            }

            .print-sheet .space-y-6 > * + * {
                margin-top: 0.4rem !important;
            }

            .print-sheet h2,
            .print-sheet h3 {
                font-size: 13px !important;
                line-height: 1.2 !important;
                margin: 0 !important;
            }

            .print-sheet .mt-1,
            .print-sheet .mt-3,
            .print-sheet .mt-4,
            .print-sheet .mt-5 {
                margin-top: 0.3rem !important;
            }

            .print-sheet .text-xl {
                font-size: 14px !important;
                line-height: 1.2 !important;
            }

            .print-sheet .text-sm,
            .print-sheet .text-xs,
            .print-sheet .text-base {
                font-size: 11px !important;
                line-height: 1.25 !important;
            }

            .print-sheet .rounded-2xl,
            .print-sheet .rounded-xl {
                border-radius: 8px !important;
            }

            .print-sheet .px-4,
            .print-sheet .py-3,
            .print-sheet .p-6 {
                padding: 0.45rem !important;
            }

            .print-sheet .grid {
                gap: 0.35rem !important;
            }

            .print-children-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 0.3rem !important;
            }

            .print-children-item {
                padding: 0.35rem !important;
            }

            .print-children-item p {
                margin: 0 !important;
            }

            .print-children-item .text-\[0\.65rem\] {
                font-size: 9px !important;
            }
        }
    </style>

    <div class="space-y-5 print-sheet print-single-page">
        <div class="box bg-[color:var(--brand-soft)] p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-[color:var(--brand-forest)]">Ringkasan Proses Pembayaran</h2>
                    <p class="mt-1 text-sm text-zinc-600">Portal sedang menyemak dan mengemaskini keputusan pembayaran anda.</p>
                </div>
                <div class="hidden rounded-xl border px-4 py-2 text-sm font-extrabold tracking-wider sm:inline-flex {{ $stamp['classes'] }}">
                    {{ $stamp['label'] }}
                </div>
            </div>
            <p class="mt-3 text-sm font-medium text-zinc-700">{{ $stamp['note'] }}</p>
        </div>

        <div class="box p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-[color:var(--brand-forest)]">Status Transaksi</h2>
            <p class="mt-1 text-sm text-zinc-600">Kod keluarga {{ $transaction->familyBilling->family_code }} · {{ $transaction->familyBilling->billing_year }}</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-zinc-200 bg-[color:var(--brand-soft)] px-4 py-3 text-sm">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Jumlah</p>
                    <p class="text-xl font-bold text-[color:var(--brand-green)]">RM {{ number_format($transaction->amount, 2) }}</p>
                </div>
                <div class="rounded-2xl border border-zinc-200 bg-[color:var(--brand-soft)] px-4 py-3 text-sm">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Status</p>
                    <p class="text-xl font-bold text-zinc-700">{{ $statusDisplay }}</p>
                </div>
                <div class="rounded-2xl border border-zinc-200 bg-[color:var(--brand-soft)] px-4 py-3 text-sm">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Bayar Pada</p>
                    <p class="text-xl font-bold text-zinc-700">{{ $transaction->paid_at?->format('d M Y H:i') ?? '-' }}</p>
                </div>
            </div>

            <div class="mt-4 space-y-2 text-sm text-zinc-600">
                <p>Order ID: {{ $transaction->external_order_display }}</p>
                <p>Bill Code: {{ $transaction->provider_bill_code }}</p>
                <p>Return Status: {{ $transaction->return_status ? ucfirst($transaction->return_status) : 'Pending completion' }}</p>
                <p>Provider Ref No: {{ $transaction->provider_ref_no ?? '-' }}</p>
                <p>Invoice: {{ $transaction->provider_invoice_no ?? 'Belum dijana' }}</p>
            </div>

            <div class="no-print mt-4 flex flex-wrap gap-2">
                <a href="{{ route('parent.payments.receipt', $transaction->external_order_id) }}" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">Muat Turun Resit</a>
                <a href="{{ $receiptUrl }}" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">Buka Resit Web</a>
            </div>
        </div>

        <div class="box bg-[color:var(--brand-soft)] p-5 sm:p-6 print-optional">
            <h3 class="text-base font-semibold text-[color:var(--brand-forest)]">Ringkasan Proses Pembayaran</h3>
            <div class="mt-3 grid gap-2 text-sm text-zinc-700">
                <p>Gerbang pembayaran: <span class="font-semibold uppercase">{{ $transaction->payment_provider }}</span></p>
                <p>Portal menerima return URL: <span class="font-semibold">{{ $hasReturn ? 'Ya' : 'Belum' }}</span></p>
                <p>Portal menerima callback: <span class="font-semibold">{{ $hasCallback ? 'Ya' : 'Belum' }}</span></p>
                <p>Return picked up by portal: <span class="font-semibold">{{ $gatewaySeen ? 'Ya' : 'Belum' }}</span></p>
                <p>Status akhir portal: <span class="font-semibold">{{ $statusDisplay }}</span></p>
                <p>Sebab status: <span class="font-semibold">{{ $transaction->status_reason ?: '-' }}</span></p>
                <p>Kemaskini terakhir: <span class="font-semibold">{{ $transaction->updated_at?->format('d M Y H:i') ?? '-' }}</span></p>
            </div>
        </div>

        <div class="box p-5 sm:p-6">
            <h3 class="text-base font-semibold text-[color:var(--brand-forest)]">Maklumat Pembayar</h3>
            <div class="mt-3 grid gap-1 text-sm text-zinc-600">
                <p>Nama: {{ $transaction->payer_name ?? '-' }}</p>
                <p>Email: {{ $transaction->payer_email ?? '-' }}</p>
                <p>Telefon: {{ $transaction->payer_phone ?? '-' }}</p>
            </div>
        </div>

        <div class="box p-5 sm:p-6">
            <h3 class="text-base font-semibold text-[color:var(--brand-forest)]">Senarai Anak</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 print-children-grid">
                @foreach($familyChildren as $child)
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm print-children-item">
                        <p class="font-semibold text-zinc-900">{{ $child->full_name }}</p>
                        <p class="text-xs text-zinc-500">{{ $child->class_name }}</p>
                        <p class="text-[0.65rem] text-zinc-500">No. Murid: {{ $child->student_no }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts::app>
