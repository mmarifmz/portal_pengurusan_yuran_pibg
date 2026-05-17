<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use App\Models\PaymentAllocation;
use App\Models\PaymentCampaignSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\FamilyPaymentPlanService;
use App\Services\PaymentReportingService;
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

it('lets a parent change from ansuran 2 kali to bayaran penuh before any payment is made', function () {
    enableAllSplitCampaign();
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-CHANGE-TO-FULL');

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.plan.select', $billing), [
            'plan_type' => FamilyPaymentPlan::PLAN_TWO_TIMES,
        ])
        ->assertRedirect(route('parent.payments.checkout', $billing));

    $originalPlan = $billing->fresh()->paymentPlan()->with('installments.transactions')->firstOrFail();

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('payment-plan.change', $originalPlan))
        ->assertRedirect(route('parent.payments.review', ['familyBilling' => $billing, 'select_plan' => 1]));

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.plan.select', $billing), [
            'plan_type' => FamilyPaymentPlan::PLAN_FULL,
        ])
        ->assertRedirect(route('parent.payments.checkout', $billing));

    $billing->refresh();
    $activePlan = $billing->paymentPlan()->with('installments')->first();
    $cancelledPlan = $billing->paymentPlans()->whereKey($originalPlan->id)->first();

    expect($cancelledPlan)->not->toBeNull();
    expect($cancelledPlan->status)->toBe(FamilyPaymentPlan::STATUS_CANCELLED);
    expect($billing->paymentPlans()->count())->toBe(2);
    expect($activePlan)->not->toBeNull();
    expect($activePlan->plan_type)->toBe(FamilyPaymentPlan::PLAN_FULL);
    expect($activePlan->status)->toBe(FamilyPaymentPlan::STATUS_PENDING);
});

it('lets a parent change from bayaran penuh to ansuran 3 kali before any payment is made', function () {
    enableAllSplitCampaign();
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-CHANGE-TO-3X');

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.plan.select', $billing), [
            'plan_type' => FamilyPaymentPlan::PLAN_FULL,
        ])
        ->assertRedirect(route('parent.payments.checkout', $billing));

    $originalPlan = $billing->fresh()->paymentPlan()->with('installments.transactions')->firstOrFail();

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('payment-plan.change', $originalPlan))
        ->assertRedirect(route('parent.payments.review', ['familyBilling' => $billing, 'select_plan' => 1]));

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.plan.select', $billing), [
            'plan_type' => FamilyPaymentPlan::PLAN_THREE_TIMES,
        ])
        ->assertRedirect(route('parent.payments.checkout', $billing));

    $billing->refresh();
    $activePlan = $billing->paymentPlan()->with('installments')->first();
    $cancelledPlan = $billing->paymentPlans()->whereKey($originalPlan->id)->first();

    expect($cancelledPlan)->not->toBeNull();
    expect($cancelledPlan->status)->toBe(FamilyPaymentPlan::STATUS_CANCELLED);
    expect($billing->paymentPlans()->count())->toBe(2);
    expect($activePlan)->not->toBeNull();
    expect($activePlan->plan_type)->toBe(FamilyPaymentPlan::PLAN_THREE_TIMES);
    expect($activePlan->installments->count())->toBe(3);
});

it('does not let a parent change plan after the first installment is paid', function () {
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-CHANGE-BLOCKED');
    $planService = app(FamilyPaymentPlanService::class);
    $plan = $planService->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);
    $firstInstallment = $plan->installments()->where('installment_no', 1)->firstOrFail();

    $firstInstallment->forceFill([
        'status' => FamilyPaymentInstallment::STATUS_PAID,
        'paid_at' => now(),
    ])->save();

    $plan = $planService->recalculatePlan($plan->fresh());

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('payment-plan.change', $plan))
        ->assertRedirect(route('parent.payments.checkout', $billing))
        ->assertSessionHasErrors('payment_plan');

    expect($plan->fresh()->status)->not->toBe(FamilyPaymentPlan::STATUS_CANCELLED);
    expect($billing->fresh()->paymentPlans()->count())->toBe(1);
});

it('shows the change payment plan button only when no payment has been made and includes interactive styling', function () {
    enableAllSplitCampaign();
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-CHANGE-UI');
    $planService = app(FamilyPaymentPlanService::class);
    $planService->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->get(route('parent.payments.review', $billing))
        ->assertOk()
        ->assertSee('Tukar Pilihan Bayaran')
        ->assertSee('Belum buat bayaran? Anda boleh kembali dan pilih kaedah bayaran lain.')
        ->assertSee('ph ph-arrows-clockwise', false)
        ->assertSee('class="btn-change-plan"', false)
        ->assertSee('.btn-change-plan:hover', false)
        ->assertSee('.btn-change-plan:active', false)
        ->assertSee('.change-plan-box', false);

    $paidPlan = $billing->fresh()->paymentPlan()->with('installments')->firstOrFail();
    $firstInstallment = $paidPlan->installments()->where('installment_no', 1)->firstOrFail();
    $firstInstallment->forceFill([
        'status' => FamilyPaymentInstallment::STATUS_PAID,
        'paid_at' => now(),
    ])->save();
    $planService->recalculatePlan($paidPlan->fresh());

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->get(route('parent.payments.review', $billing))
        ->assertOk()
        ->assertDontSee('Tukar Pilihan Bayaran')
        ->assertDontSee('Belum buat bayaran? Anda boleh kembali dan pilih kaedah bayaran lain.')
        ->assertSee('Pilihan bayaran tidak boleh ditukar kerana bayaran telah dibuat.');
});

it('creates a fresh toyyibpay bill for a new installment attempt so parents can re-choose payment method', function () {
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-FRESH-BILL');
    $plan = app(FamilyPaymentPlanService::class)->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);
    $installment = $plan->installments()->where('installment_no', 1)->firstOrFail();

    createPendingInstallmentTransaction($installment, 'PBG-INSTALL-OLD', 'BILLINST-OLD');

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('createBill')->once()->andReturn('BILLINST-NEW');
    $toyyib->shouldReceive('paymentUrl')
        ->once()
        ->with('BILLINST-NEW')
        ->andReturn('https://toyyibpay.com/BILLINST-NEW');
    $this->app->instance(ToyyibPayService::class, $toyyib);

    $response = $this
        ->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.installments.pay', $installment), [
            'payer_name' => 'Puan Alya',
            'payer_email' => 'parent@example.test',
            'payer_phone' => '0123456789',
        ]);

    $response->assertRedirect('https://toyyibpay.com/BILLINST-NEW');

    expect($installment->fresh()->billcode)->toBe('BILLINST-NEW');
    expect($installment->fresh()->status)->toBe(FamilyPaymentInstallment::STATUS_REDIRECTED);
    expect($installment->transactions()->count())->toBe(2);
    expect($installment->transactions()->latest('id')->value('provider_bill_code'))->toBe('BILLINST-NEW');
});

it('includes optional donation in the installment bill amount and stores separate allocations', function () {
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-DONATION-BILL');
    $plan = app(FamilyPaymentPlanService::class)->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);
    $installment = $plan->installments()->where('installment_no', 1)->firstOrFail();

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('createBill')
        ->once()
        ->withArgs(function (array $payload): bool {
            return ($payload['billAmount'] ?? null) === 7000
                && str_contains((string) ($payload['billDescription'] ?? ''), 'Sumbangan Tambahan: RM20.00');
        })
        ->andReturn('BILLINST-DONATION');
    $toyyib->shouldReceive('paymentUrl')
        ->once()
        ->with('BILLINST-DONATION')
        ->andReturn('https://toyyibpay.com/BILLINST-DONATION');
    $this->app->instance(ToyyibPayService::class, $toyyib);

    $response = $this
        ->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.installments.pay', $installment), [
            'payer_name' => 'Puan Alya',
            'payer_email' => 'parent@example.test',
            'payer_phone' => '0123456789',
            'installment_donation_choice' => [
                $installment->id => '20',
            ],
        ]);

    $response->assertRedirect('https://toyyibpay.com/BILLINST-DONATION');

    $transaction = $installment->transactions()->latest('id')->firstOrFail();
    $allocations = $transaction->allocations()->orderBy('allocation_type')->get();

    expect((float) $transaction->amount)->toBe(70.0);
    expect($allocations)->toHaveCount(2);
    expect($allocations->where('allocation_type', PaymentAllocation::TYPE_YURAN)->value('amount'))->toBe('50.00');
    expect($allocations->where('allocation_type', PaymentAllocation::TYPE_SUMBANGAN_TAMBAHAN)->value('amount'))->toBe('20.00');
    expect($allocations->pluck('status')->unique()->all())->toBe([PaymentAllocation::STATUS_PENDING]);
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

it('records donation separately from yuran and keeps the yuran balance unchanged after installment payment', function () {
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-DONATION-SETTLE');
    $plan = app(FamilyPaymentPlanService::class)->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);
    $installment = $plan->installments()->where('installment_no', 1)->firstOrFail();

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('createBill')->once()->andReturn('BILLINST-DONATION-SETTLE');
    $toyyib->shouldReceive('paymentUrl')
        ->once()
        ->with('BILLINST-DONATION-SETTLE')
        ->andReturn('https://toyyibpay.com/BILLINST-DONATION-SETTLE');
    $toyyib->shouldReceive('verifyCallbackHash')->once()->andReturnTrue();
    $toyyib->shouldReceive('getBillTransactions')
        ->once()
        ->with('BILLINST-DONATION-SETTLE')
        ->andReturn([
            [
                'billExternalReferenceNo' => 'PBG-INSTALL-DONATION-SETTLE',
                'billpaymentAmount' => '70.00',
                'billpaymentInvoiceNo' => 'TP-INSTALL-DON-001',
                'billPaymentDate' => '2026-05-18 10:00:00',
            ],
        ]);
    $this->app->instance(ToyyibPayService::class, $toyyib);

    $createResponse = $this
        ->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.installments.pay', $installment), [
            'payer_name' => 'Puan Alya',
            'payer_email' => 'parent@example.test',
            'payer_phone' => '0123456789',
            'installment_donation_choice' => [
                $installment->id => '20',
            ],
        ]);

    $createResponse->assertRedirect('https://toyyibpay.com/BILLINST-DONATION-SETTLE');

    $transaction = $installment->transactions()->latest('id')->firstOrFail();
    $transaction->update([
        'external_order_id' => 'PBG-INSTALL-DONATION-SETTLE',
    ]);

    $callbackResponse = $this->post(route('parent.payments.toyyibpay.callback'), [
        'status' => '1',
        'order_id' => 'PBG-INSTALL-DONATION-SETTLE',
        'refno' => 'REF-INSTALL-DON-001',
        'hash' => 'valid-hash',
        'billcode' => 'BILLINST-DONATION-SETTLE',
    ]);

    $callbackResponse->assertOk();

    $plan->refresh();
    $billing->refresh();
    $transaction->refresh();
    $allocations = $transaction->allocations()->get()->keyBy('allocation_type');

    expect((float) $transaction->amount)->toBe(70.0);
    expect((float) $transaction->fee_amount_paid)->toBe(50.0);
    expect((float) $transaction->donation_amount)->toBe(20.0);
    expect((float) $plan->paid_amount)->toBe(50.0);
    expect((float) $plan->balance_amount)->toBe(50.0);
    expect((float) $billing->paid_amount)->toBe(50.0);
    expect($billing->status)->toBe('partial');
    expect($allocations[PaymentAllocation::TYPE_YURAN]->status)->toBe(PaymentAllocation::STATUS_PAID);
    expect($allocations[PaymentAllocation::TYPE_SUMBANGAN_TAMBAHAN]->status)->toBe(PaymentAllocation::STATUS_PAID);
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

it('does not duplicate yuran or donation stats when a donation callback is repeated', function () {
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-DUP-DONATION');
    $plan = app(FamilyPaymentPlanService::class)->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);
    $installment = $plan->installments()->where('installment_no', 1)->firstOrFail();

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('createBill')->once()->andReturn('BILLINST-DUP-DONATION');
    $toyyib->shouldReceive('paymentUrl')
        ->once()
        ->with('BILLINST-DUP-DONATION')
        ->andReturn('https://toyyibpay.com/BILLINST-DUP-DONATION');
    $toyyib->shouldReceive('verifyCallbackHash')->twice()->andReturnTrue();
    $toyyib->shouldReceive('getBillTransactions')
        ->once()
        ->with('BILLINST-DUP-DONATION')
        ->andReturn([
            [
                'billExternalReferenceNo' => 'PBG-INSTALL-DUP-DONATION',
                'billpaymentAmount' => '70.00',
                'billpaymentInvoiceNo' => 'TP-INSTALL-DUP-DONATION',
                'billPaymentDate' => '2026-05-18 10:30:00',
            ],
        ]);
    $this->app->instance(ToyyibPayService::class, $toyyib);

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->post(route('parent.payments.installments.pay', $installment), [
            'payer_name' => 'Puan Alya',
            'payer_email' => 'parent@example.test',
            'payer_phone' => '0123456789',
            'installment_donation_choice' => [
                $installment->id => '20',
            ],
        ])
        ->assertRedirect('https://toyyibpay.com/BILLINST-DUP-DONATION');

    $transaction = $installment->transactions()->latest('id')->firstOrFail();
    $transaction->update([
        'external_order_id' => 'PBG-INSTALL-DUP-DONATION',
    ]);

    $payload = [
        'status' => '1',
        'order_id' => 'PBG-INSTALL-DUP-DONATION',
        'refno' => 'REF-INSTALL-DUP-DONATION',
        'hash' => 'valid-hash',
        'billcode' => 'BILLINST-DUP-DONATION',
    ];

    $this->post(route('parent.payments.toyyibpay.callback'), $payload)->assertOk();
    $this->post(route('parent.payments.toyyibpay.callback'), $payload)->assertOk();

    $reporting = app(PaymentReportingService::class);
    $stats = $reporting->dashboardStats(now()->year);

    expect((float) $billing->fresh()->paid_amount)->toBe(50.0);
    expect((float) $transaction->fresh()->donation_amount)->toBe(20.0);
    expect((float) $stats['total_yuran_collected'])->toBe(50.0);
    expect((float) $stats['total_sumbangan_tambahan_collected'])->toBe(20.0);
    expect(PaymentAllocation::query()->where('family_billing_id', $billing->id)->where('allocation_type', PaymentAllocation::TYPE_SUMBANGAN_TAMBAHAN)->where('status', PaymentAllocation::STATUS_PAID)->count())->toBe(1);
});

it('renders the donation section on unpaid installment cards', function () {
    enableAllSplitCampaign();
    [$billing, $parent] = createParentFamilyBilling('SSP-INSTALL-DONATION-UI');
    app(FamilyPaymentPlanService::class)->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);

    $this->actingAs($parent)
        ->withSession(parentBillingSession($billing))
        ->get(route('parent.payments.review', $billing))
        ->assertOk()
        ->assertSee('Sumbangan Tambahan PIBG')
        ->assertSee('Sumbangan tambahan adalah pilihan. Jumlah ini akan direkodkan berasingan daripada yuran.')
        ->assertSee('Jumlah bayaran');
});

it('calculates leaderboard completion using fully paid families only while counting partial yuran collections', function () {
    $makeBilling = function (string $familyCode, string $studentNo, string $email, string $phone): FamilyBilling {
        $billing = FamilyBilling::query()->create([
            'family_code' => $familyCode,
            'billing_year' => now()->year,
            'fee_amount' => 100,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        Student::query()->create([
            'student_no' => $studentNo,
            'family_code' => $familyCode,
            'full_name' => 'Alya Batrisyia '.$familyCode,
            'class_name' => '6 Bestari',
            'parent_name' => 'Puan '.$familyCode,
            'parent_phone' => $phone,
            'parent_email' => $email,
            'billing_year' => now()->year,
        ]);

        return $billing;
    };

    $paidBilling = $makeBilling('SSP-LEADERBOARD-PAID', 'SSP-LB-001', 'lb-paid@example.test', '0120000001');
    $partialBilling = $makeBilling('SSP-LEADERBOARD-PARTIAL', 'SSP-LB-002', 'lb-partial@example.test', '0120000002');
    $unpaidBilling = $makeBilling('SSP-LEADERBOARD-UNPAID', 'SSP-LB-003', 'lb-unpaid@example.test', '0120000003');

    $planService = app(FamilyPaymentPlanService::class);

    $paidPlan = $planService->createPlan($paidBilling, FamilyPaymentPlan::PLAN_FULL);
    $paidInstallment = $paidPlan->installments()->firstOrFail();
    $paidInstallment->forceFill([
        'status' => FamilyPaymentInstallment::STATUS_PAID,
        'paid_at' => now(),
    ])->save();
    $planService->recalculatePlan($paidPlan->fresh());

    $partialPlan = $planService->createPlan($partialBilling, FamilyPaymentPlan::PLAN_TWO_TIMES);
    $partialInstallment = $partialPlan->installments()->where('installment_no', 1)->firstOrFail();
    $partialInstallment->forceFill([
        'status' => FamilyPaymentInstallment::STATUS_PAID,
        'paid_at' => now(),
    ])->save();
    $planService->recalculatePlan($partialPlan->fresh());

    $reporting = app(PaymentReportingService::class);
    $leaderboardRow = $reporting->classLeaderboard(now()->year)->firstWhere('class_name', '6 Bestari');
    $stats = $reporting->dashboardStats(now()->year);

    expect($leaderboardRow)->not->toBeNull();
    expect($leaderboardRow['total_families'])->toBe(3);
    expect($leaderboardRow['fully_paid_families'])->toBe(1);
    expect($leaderboardRow['partial_paid_families'])->toBe(1);
    expect($leaderboardRow['unpaid_families'])->toBe(1);
    expect((float) $leaderboardRow['yuran_collected'])->toBe(150.0);
    expect((float) $leaderboardRow['baki_tertunggak'])->toBe(150.0);
    expect((float) $leaderboardRow['completion_percent'])->toBe(33.33);
    expect((int) $stats['fully_paid_families'])->toBeGreaterThanOrEqual(1);
    expect((int) $stats['partial_paid_families'])->toBeGreaterThanOrEqual(1);
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
