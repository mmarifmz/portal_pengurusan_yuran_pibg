<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\User;
use App\Services\ToyyibPayService;

it('shows latest bill code and check gateway button in payment funnel monitor', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-CHK1',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'external_order_id' => 'PBG-CHK-001',
        'provider_bill_code' => 'BILLCODE123',
        'amount' => 100,
        'payer_phone' => '0123456789',
        'status' => 'pending',
        'return_status' => 'pending completion',
    ]);

    $response = $this->actingAs($admin)->get(route('system.payment-funnel-monitor.index', [
        'billing_year' => now()->year,
        'gateway_status' => 'pending',
    ]));

    $response->assertOk();
    $response->assertSee('Latest Bill Code');
    $response->assertSee('BILLCODE123');
    $response->assertSee('Check with Gateway');
});

it('checks gateway without refresh and syncs pending transaction to success', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-CHK2',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'external_order_id' => 'PBG-CHK-002',
        'provider_bill_code' => 'BILLCODE456',
        'amount' => 100,
        'payer_name' => 'Parent Test',
        'payer_email' => 'parent@example.test',
        'payer_phone' => '0123456789',
        'status' => 'pending',
        'return_status' => 'pending completion',
        'raw_return' => [
            'outstanding_at_checkout' => 100,
        ],
    ]);

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('getBillTransactions')
        ->once()
        ->with('BILLCODE456')
        ->andReturn([
            [
                'billExternalReferenceNo' => 'PBG-CHK-002',
                'billpaymentStatus' => '1',
                'billpaymentStatusName' => 'Successful',
                'billpaymentAmount' => '100.00',
                'billpaymentInvoiceNo' => 'TP123456789',
                'billPaymentDate' => '2026-04-25 13:00:00',
            ],
        ]);

    $this->app->instance(ToyyibPayService::class, $toyyib);

    $response = $this
        ->actingAs($admin)
        ->postJson(route('system.payment-funnel-monitor.check-gateway'), [
            'transaction_id' => $transaction->id,
            'billing_year' => now()->year,
            'gateway_status' => 'pending',
            'sort_by' => 'timestamp',
            'sort_dir' => 'desc',
        ]);

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('payload.gateway_status', 'success');

    $transaction->refresh();
    $billing->refresh();

    expect($transaction->status)->toBe('success');
    expect($transaction->return_status)->toBe('successful');
    expect($transaction->provider_invoice_no)->toBe('TP123456789');
    expect($transaction->receipt_notified_at)->not->toBeNull();
    expect((float) $billing->paid_amount)->toBe(100.0);
    expect($billing->status)->toBe('paid');
});