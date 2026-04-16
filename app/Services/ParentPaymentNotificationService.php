<?php

namespace App\Services;

use App\Models\FamilyPaymentTransaction;
use RuntimeException;

class ParentPaymentNotificationService
{
    public function __construct(private readonly WhatsAppTacSender $whatsAppTacSender)
    {
    }

    public function sendPaymentReceipt(
        FamilyPaymentTransaction $transaction,
        ?string $phone = null,
        ?string $parentName = null
    ): array {
        $transaction->ensureReceiptUuid();

        $targetPhone = trim((string) ($phone ?: $transaction->payer_phone));

        if ($targetPhone === '') {
            throw new RuntimeException('No parent phone number available for receipt notification.');
        }

        $greeting = $parentName ? "Assalamualaikum {$parentName}," : 'Assalamualaikum,';

        $lines = [
            $greeting,
            '',
            $transaction->status === 'success'
                ? 'Pembayaran PIBG anda telah diterima.'
                : 'Maklumat pembayaran PIBG anda telah dikemaskini.',
            'Kod keluarga: '.$transaction->familyBilling->family_code,
            'Jumlah: RM'.number_format((float) $transaction->amount, 2),
            'Order ID: '.$transaction->external_order_id,
        ];

        if ($transaction->provider_invoice_no) {
            $lines[] = 'Invoice: '.$transaction->provider_invoice_no;
        }

        if ($transaction->paid_at) {
            $lines[] = 'Tarikh bayar: '.$transaction->paid_at->format('d/m/Y h:i A');
        }

        $lines[] = 'Resit web: '.$this->receiptUrl($transaction);

        $delivery = $this->whatsAppTacSender->sendMessage($targetPhone, implode("\n", $lines));

        $transaction->forceFill([
            'receipt_message_id' => $delivery['message_id'] ?? null,
            'receipt_notified_at' => now(),
        ])->save();

        return $delivery;
    }

    public function receiptUrl(FamilyPaymentTransaction $transaction): string
    {
        $transaction->ensureReceiptUuid();

        return route('receipts.show', ['receiptUuid' => $transaction->receipt_uuid]);
    }
}
