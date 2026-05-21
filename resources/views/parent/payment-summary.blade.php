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
        $statusDisplay = $receiptContext['payment_status_label'] ?? ($status === 'superseded' ? 'Dibatalkan' : ucfirst($status));
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

            @if (! empty($receiptContext['has_installment']))
                <div class="mt-4 grid gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Ansuran</p>
                        <p class="text-lg font-bold text-zinc-900">{{ $receiptContext['installment_label'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Bayaran Transaksi Ini</p>
                        <p class="text-lg font-bold text-zinc-900">RM {{ number_format((float) $receiptContext['transaction_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Jumlah Dibayar</p>
                        <p class="text-lg font-bold text-emerald-700">RM {{ number_format((float) ($receiptContext['total_paid_to_date'] ?? 0), 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Baki Bayaran</p>
                        <p class="text-lg font-bold {{ ! empty($receiptContext['fully_paid']) ? 'text-emerald-700' : 'text-amber-700' }}">
                            RM {{ number_format((float) ($receiptContext['remaining_balance'] ?? 0), 2) }}
                        </p>
                    </div>
                </div>
            @endif

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
                @if ((string) $transaction->status === 'success' && ! empty($teacherNotificationShareUrl))
                    <button type="button" data-share-teacher-trigger class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">
                        Kongsi Resit Kepada Guru Kelas
                    </button>
                @endif
            </div>

            @if (! empty($teacherNotificationSummary))
                <div class="mt-4 rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700" data-share-teacher-status>
                    {{ $teacherNotificationSummary['label'] }}
                </div>
            @elseif ((string) $transaction->status === 'success' && ! empty($teacherNotificationShareUrl))
                <div class="mt-4 hidden rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700" data-share-teacher-status></div>
            @endif

            @if ((string) $transaction->status === 'success' && ! empty($teacherNotificationShareUrl))
                <div class="mt-3 hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" data-share-teacher-flash></div>
            @endif
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
                <p>Niat sumbangan: {{ $transaction->donation_intention ?? '-' }}</p>
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

    @if ((string) $transaction->status === 'success' && ! empty($teacherNotificationShareUrl))
        <div id="shareTeacherSummaryModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl border border-zinc-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Pengesahan</p>
                        <h3 class="mt-1 text-lg font-semibold text-zinc-900">Kongsi Resit Kepada Guru Kelas</h3>
                    </div>
                    <button type="button" data-share-teacher-close class="rounded-xl border border-zinc-300 px-3 py-1 text-xs font-semibold text-zinc-700 hover:bg-zinc-100">
                        Tutup
                    </button>
                </div>
                <p class="mt-4 text-sm leading-6 text-zinc-600">
                    Makluman bayaran akan dihantar kepada guru kelas melalui WhatsApp.
                </p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" data-share-teacher-close class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-100">
                        Batal
                    </button>
                    <button type="button" data-share-teacher-confirm data-url="{{ $teacherNotificationShareUrl }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        Ya, Hantar Kepada Guru
                    </button>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('shareTeacherSummaryModal');
                const trigger = document.querySelector('[data-share-teacher-trigger]');
                const closeButtons = document.querySelectorAll('[data-share-teacher-close]');
                const confirmButton = document.querySelector('[data-share-teacher-confirm]');
                const statusBox = document.querySelector('[data-share-teacher-status]');
                const flashBox = document.querySelector('[data-share-teacher-flash]');

                if (!modal || !trigger || !confirmButton) {
                    return;
                }

                trigger.addEventListener('click', () => {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });

                closeButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    });
                });

                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                });

                confirmButton.addEventListener('click', async () => {
                    confirmButton.disabled = true;

                    try {
                        const response = await fetch(confirmButton.dataset.url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                        });
                        const payload = await response.json();

                        flashBox.textContent = payload.message || 'Status penghantaran sedang dikemas kini.';
                        flashBox.classList.remove('hidden', 'border-rose-200', 'bg-rose-50', 'text-rose-700');
                        flashBox.classList.add(payload.ok ? 'border-emerald-200' : 'border-rose-200');
                        flashBox.classList.add(payload.ok ? 'bg-emerald-50' : 'bg-rose-50');
                        flashBox.classList.add(payload.ok ? 'text-emerald-700' : 'text-rose-700');

                        if (payload.status_label) {
                            statusBox.textContent = payload.status_label;
                            statusBox.classList.remove('hidden');
                        }

                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    } catch (_error) {
                        flashBox.textContent = 'Makluman tidak dapat dihantar buat masa ini. Sila cuba lagi.';
                        flashBox.classList.remove('hidden');
                        flashBox.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-700');
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    } finally {
                        confirmButton.disabled = false;
                    }
                });
            });
        </script>
    @endif
</x-layouts::app>
