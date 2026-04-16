<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use App\Models\User;

it('creates parent tac via api notification endpoint', function () {
    config()->set('services.whatsapp.enabled', true);

    User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
        'email' => 'parent.api@example.test',
    ]);

    $response = $this->postJson(route('api.notifications.parent-tac'), [
        'phone' => '0123456789',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true);

    expect(ParentLoginOtp::query()->where('phone', '0123456789')->count())->toBe(1);
});

it('renders public web receipt by receipt uuid', function () {
    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F888',
        'billing_year' => 2026,
        'fee_amount' => 120,
        'paid_amount' => 120,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP2001',
        'family_code' => 'SSP-F888',
        'full_name' => 'Alya Zahra',
        'class_name' => '6 Bestari',
        'parent_name' => 'Pn. Huda',
        'parent_phone' => '0123456789',
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'PBG-TEST-001',
        'amount' => 120,
        'payer_email' => 'payer@example.test',
        'payer_phone' => '0123456789',
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $response = $this->get(route('receipts.show', $transaction->receipt_uuid));

    $response
        ->assertOk()
        ->assertSee('Resit Bayaran')
        ->assertSee('SSP-F888')
        ->assertSee('PBG-TEST-001');
});

it('sends payment receipt notification through api endpoint', function () {
    config()->set('services.whatsapp.enabled', true);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F777',
        'billing_year' => 2026,
        'fee_amount' => 80,
        'paid_amount' => 80,
        'status' => 'paid',
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'PBG-TEST-002',
        'amount' => 80,
        'payer_email' => 'payer@example.test',
        'payer_phone' => '0123456789',
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $response = $this->postJson(route('api.transactions.notify-receipt', $transaction), [
        'parent_name' => 'Pn. Zarina',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($transaction->fresh()->receipt_notified_at)->not->toBeNull();
});
