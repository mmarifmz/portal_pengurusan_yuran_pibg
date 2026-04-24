<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Services\ParentPaymentNotificationService;
use App\Services\WhatsAppTacSender;

it('formats payment completion whatsapp message close to requested template', function () {
    config()->set('services.parent_receipt_whatsapp_max_chars', 1000);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-0252',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'PBG-260424-LJ00-SSP',
        'provider_invoice_no' => 'TP2604242679087716',
        'amount' => 100,
        'payer_email' => 'payer@example.test',
        'payer_phone' => '0111111111',
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $sender = \Mockery::mock(WhatsAppTacSender::class);
    $sender->shouldReceive('sendMessage')
        ->once()
        ->with('0111111111', \Mockery::on(function (string $message): bool {
            return str_contains($message, 'Assalamualaikum dan Salam Sejahtera,')
                && str_contains($message, 'Pembayaran PIBG anda telah berjaya diterima.')
                && str_contains($message, 'Berikut adalah maklumat pembayaran:')
                && str_contains($message, '• Kod Keluarga: SSP-0252')
                && str_contains($message, '• Jumlah: RM100.00')
                && str_contains($message, '• Order ID:')
                && str_contains($message, '• Invoice: TP2604242679087716')
                && str_contains($message, 'Resit Web:')
                && str_contains($message, 'Sila login ke dashboard ibu bapa menggunakan nombor telefon ini.')
                && str_contains($message, 'Membuat sumbangan tambahan kepada PIBG SSP')
                && mb_strlen($message) <= 1000;
        }))
        ->andReturn([
            'status' => 'testing',
            'message_id' => 'MSG-001',
        ]);

    $service = new ParentPaymentNotificationService($sender);
    $service->sendPaymentReceipt($transaction, null, null);

    expect($transaction->fresh()->receipt_notified_at)->not->toBeNull();
});

it('falls back to compact receipt message when whatsapp max chars is low', function () {
    config()->set('services.parent_receipt_whatsapp_max_chars', 220);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-0900',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'PBG-260424-ABCD-SSP',
        'provider_invoice_no' => 'TP2604249999',
        'amount' => 100,
        'payer_email' => 'payer@example.test',
        'payer_phone' => '0123456789',
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $sender = \Mockery::mock(WhatsAppTacSender::class);
    $sender->shouldReceive('sendMessage')
        ->once()
        ->with('0123456789', \Mockery::on(function (string $message): bool {
            return mb_strlen($message) <= 220
                && str_contains($message, 'Resit We')
                && ! str_contains($message, 'Di dalam dashboard, anda boleh:');
        }))
        ->andReturn([
            'status' => 'testing',
            'message_id' => 'MSG-002',
        ]);

    $service = new ParentPaymentNotificationService($sender);
    $service->sendPaymentReceipt($transaction);
});
