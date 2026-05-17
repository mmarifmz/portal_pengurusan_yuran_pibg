<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use App\Models\PaymentCampaignSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\FamilyPaymentPlanService;
use App\Services\ToyyibPayService;

function createParentFamilyBilling(string $familyCode = 'SSP-INSTALL-001'): array
{
    $billing = FamilyBilling::query()->create([
        'family_code' => $familyCode,
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP1001',
        'family_code' => $familyCode,
        'full_name' => 'Alya Batrisyia',
        'class_name' => '6 Bestari',
        'parent_name' => 'Puan Alya',
        'parent_phone' => '0123456789',
        'parent_email' => 'parent@example.test',
        'billing_year' => now()->year,
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
        'email' => 'parent@example.test',
        'name' => 'Puan Alya',
        'email_verified_at' => now(),
    ]);

    return [$billing, $parent];
}

function parentBillingSession(FamilyBilling $billing): array
{
    return [
        'parent_child_selection_completed' => true,
        'parent_selected_family_billing_id' => $billing->id,
    ];
}

function createPendingInstallmentTransaction(FamilyPaymentInstallment $installment, string $externalOrderId, string $billCode): FamilyPaymentTransaction
{
    return FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $installment->family_billing_id,
        'family_payment_installment_id' => $installment->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => $externalOrderId,
        'provider_bill_code' => $billCode,
        'amount' => (float) $installment->amount,
        'payer_name' => 'Puan Alya',
        'payer_email' => 'parent@example.test',
        'payer_phone' => '0123456789',
        'status' => 'pending',
        'return_status' => 'pending completion',
        'raw_return' => [
            'installment_id' => $installment->id,
            'installment_no' => $installment->installment_no,
            'outstanding_at_checkout' => (float) $installment->amount,
        ],
    ]);
}

function enableAllSplitCampaign(): void
{
    PaymentCampaignSetting::query()->create([
        'campaign_name' => 'Kempen Ansuran Semua',
        'is_active' => true,
        'allow_single_payment' => true,
        'allow_split_payment' => true,
        'allow_split_2' => true,
        'split_2_visibility' => 'all',
        'allow_split_3' => true,
        'split_3_visibility' => 'all',
    ]);
}

it('lets a parent select the 2-time payment plan', function () {
    enableAllSplitCampaign();
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-2X');

    $response = $this
        ->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.plan.select', $billing), [
            'plan_type' => FamilyPaymentPlan::PLAN_TWO_TIMES,
        ]);

    $response->assertRedirect(route('parent.payments.checkout', $billing));

    $plan = $billing->fresh()->paymentPlan()->with('installments')->first();

    expect($plan)->not->toBeNull();
    expect($plan->plan_type)->toBe(FamilyPaymentPlan::PLAN_TWO_TIMES);
    expect($plan->installments->pluck('amount')->map(fn ($amount) => (float) $amount)->all())->toBe([50.0, 50.0]);
});

it('lets a parent select the 3-time payment plan', function () {
    enableAllSplitCampaign();
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-3X');

    $response = $this
        ->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.plan.select', $billing), [
            'plan_type' => FamilyPaymentPlan::PLAN_THREE_TIMES,
        ]);

    $response->assertRedirect(route('parent.payments.checkout', $billing));

    $plan = $billing->fresh()->paymentPlan()->with('installments')->first();

    expect($plan)->not->toBeNull();
    expect($plan->plan_type)->toBe(FamilyPaymentPlan::PLAN_THREE_TIMES);
    expect($plan->installments->pluck('amount')->map(fn ($amount) => (float) $amount)->all())->toBe([40.0, 30.0, 30.0]);
});

it('marks the plan as partial after the first instalment payment succeeds', function () {
    [$billing] = createParentFamilyBilling('SSP-INSTALL-PARTIAL');
    $plan = app(FamilyPaymentPlanService::class)->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);
    $installment = $plan->installments()->where('installment_no', 1)->firstOrFail();
    $transaction = createPendingInstallmentTransaction($installment, 'PBG-INSTALL-001', 'BILLINST001');

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('verifyCallbackHash')->once()->andReturnTrue();
    $toyyib->shouldReceive('getBillTransactions')
        ->once()
        ->with('BILLINST001')
        ->andReturn([
            [
                'billExternalReferenceNo' => 'PBG-INSTALL-001',
                'billpaymentAmount' => '50.00',
                'billpaymentInvoiceNo' => 'TP-INSTALL-001',
                'billPaymentDate' => '2026-05-17 10:00:00',
            ],
        ]);
    $this->app->instance(ToyyibPayService::class, $toyyib);

    $response = $this->post(route('parent.payments.toyyibpay.callback'), [
        'status' => '1',
        'order_id' => $transaction->external_order_id,
        'refno' => 'REF-INSTALL-001',
        'hash' => 'valid-hash',
        'billcode' => 'BILLINST001',
    ]);

    $response->assertOk();

    $plan->refresh();
    $billing->refresh();
    $installment->refresh();

    expect($installment->status)->toBe(FamilyPaymentInstallment::STATUS_PAID);
    expect((float) $plan->paid_amount)->toBe(50.0);
    expect((float) $plan->balance_amount)->toBe(50.0);
    expect($plan->status)->toBe(FamilyPaymentPlan::STATUS_PARTIAL);
    expect((float) $billing->paid_amount)->toBe(50.0);
    expect($billing->status)->toBe('partial');
});

it('marks the family as paid after the final instalment succeeds', function () {
    [$billing] = createParentFamilyBilling('SSP-INSTALL-FINAL');
    $planService = app(FamilyPaymentPlanService::class);
    $plan = $planService->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);

    $firstInstallment = $plan->installments()->where('installment_no', 1)->firstOrFail();
    $firstInstallment->forceFill([
        'status' => FamilyPaymentInstallment::STATUS_PAID,
        'paid_at' => now(),
    ])->save();
    $plan = $planService->recalculatePlan($plan->fresh());

    $secondInstallment = $plan->installments()->where('installment_no', 2)->firstOrFail();
    $transaction = createPendingInstallmentTransaction($secondInstallment, 'PBG-INSTALL-002', 'BILLINST002');

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('verifyCallbackHash')->once()->andReturnTrue();
    $toyyib->shouldReceive('getBillTransactions')
        ->once()
        ->with('BILLINST002')
        ->andReturn([
            [
                'billExternalReferenceNo' => 'PBG-INSTALL-002',
                'billpaymentAmount' => '50.00',
                'billpaymentInvoiceNo' => 'TP-INSTALL-002',
                'billPaymentDate' => '2026-05-17 11:00:00',
            ],
        ]);
    $this->app->instance(ToyyibPayService::class, $toyyib);

    $response = $this->post(route('parent.payments.toyyibpay.callback'), [
        'status' => '1',
        'order_id' => $transaction->external_order_id,
        'refno' => 'REF-INSTALL-002',
        'hash' => 'valid-hash',
        'billcode' => 'BILLINST002',
    ]);

    $response->assertOk();

    $plan->refresh();
    $billing->refresh();
    $secondInstallment->refresh();

    expect($secondInstallment->status)->toBe(FamilyPaymentInstallment::STATUS_PAID);
    expect((float) $plan->paid_amount)->toBe(100.0);
    expect((float) $plan->balance_amount)->toBe(0.0);
    expect($plan->status)->toBe(FamilyPaymentPlan::STATUS_PAID);
    expect((float) $billing->paid_amount)->toBe(100.0);
    expect($billing->status)->toBe('paid');
});

it('does not double count duplicate ToyyibPay callbacks', function () {
    [$billing] = createParentFamilyBilling('SSP-INSTALL-DUP');
    $plan = app(FamilyPaymentPlanService::class)->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);
    $installment = $plan->installments()->where('installment_no', 1)->firstOrFail();
    $transaction = createPendingInstallmentTransaction($installment, 'PBG-INSTALL-003', 'BILLINST003');

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('verifyCallbackHash')->twice()->andReturnTrue();
    $toyyib->shouldReceive('getBillTransactions')
        ->once()
        ->with('BILLINST003')
        ->andReturn([
            [
                'billExternalReferenceNo' => 'PBG-INSTALL-003',
                'billpaymentAmount' => '50.00',
                'billpaymentInvoiceNo' => 'TP-INSTALL-003',
                'billPaymentDate' => '2026-05-17 12:00:00',
            ],
        ]);
    $this->app->instance(ToyyibPayService::class, $toyyib);

    $payload = [
        'status' => '1',
        'order_id' => $transaction->external_order_id,
        'refno' => 'REF-INSTALL-003',
        'hash' => 'valid-hash',
        'billcode' => 'BILLINST003',
    ];

    $this->post(route('parent.payments.toyyibpay.callback'), $payload)->assertOk();
    $this->post(route('parent.payments.toyyibpay.callback'), $payload)->assertOk();

    $plan->refresh();
    $billing->refresh();
    $installment->refresh();

    expect((float) $plan->paid_amount)->toBe(50.0);
    expect((float) $plan->balance_amount)->toBe(50.0);
    expect((float) $billing->paid_amount)->toBe(50.0);
    expect($installment->status)->toBe(FamilyPaymentInstallment::STATUS_PAID);
});

it('keeps the existing full payment flow working', function () {
    [$billing, $parent] = createParentFamilyBilling('SSP-FULL-COMPAT');

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('createBill')->once()->andReturn('BILLFULL001');
    $toyyib->shouldReceive('paymentUrl')->once()->with('BILLFULL001')->andReturn('https://toyyibpay.com/BILLFULL001');
    $toyyib->shouldReceive('verifyCallbackHash')->once()->andReturnTrue();
    $toyyib->shouldReceive('getBillTransactions')
        ->once()
        ->with('BILLFULL001')
        ->andReturn([
            [
                'billExternalReferenceNo' => 'PBG-FULL-001',
                'billpaymentAmount' => '100.00',
                'billpaymentInvoiceNo' => 'TP-FULL-001',
                'billPaymentDate' => '2026-05-17 13:00:00',
            ],
        ]);
    $this->app->instance(ToyyibPayService::class, $toyyib);

    $createResponse = $this
        ->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.create', $billing), [
            'payer_name' => 'Puan Alya',
            'payer_email' => 'parent@example.test',
            'payer_phone' => '0123456789',
            'donation_preset' => 0,
            'donation_custom' => 0,
            'donation_intention' => '',
        ]);

    $createResponse->assertRedirect('https://toyyibpay.com/BILLFULL001');

    $transaction = FamilyPaymentTransaction::query()->latest('id')->first();
    $transaction->update([
        'external_order_id' => 'PBG-FULL-001',
    ]);

    $callbackResponse = $this->post(route('parent.payments.toyyibpay.callback'), [
        'status' => '1',
        'order_id' => 'PBG-FULL-001',
        'refno' => 'REF-FULL-001',
        'hash' => 'valid-hash',
        'billcode' => 'BILLFULL001',
    ]);

    $callbackResponse->assertOk();

    $transaction->refresh();
    $billing->refresh();

    expect($transaction->family_payment_installment_id)->toBeNull();
    expect($transaction->status)->toBe('success');
    expect((float) $billing->paid_amount)->toBe(100.0);
    expect($billing->status)->toBe('paid');
});
