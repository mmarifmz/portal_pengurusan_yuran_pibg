<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentPlan;
use App\Models\PaymentGatewaySetting;
use App\Models\Student;
use App\Models\User;
use App\Services\FamilyPaymentPlanService;
use App\Services\ToyyibPayService;

function createGatewayTestFamily(string $familyCode = 'SSP-GATEWAY-001'): array
{
    $digits = preg_replace('/\D+/', '', $familyCode) ?? '';
    $phone = '0128'.str_pad(substr($digits !== '' ? $digits : (string) abs(crc32($familyCode)), -6), 6, '0', STR_PAD_LEFT);

    $billing = FamilyBilling::query()->create([
        'family_code' => $familyCode,
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'GTW-'.$familyCode,
        'family_code' => $familyCode,
        'full_name' => 'Anak Gateway',
        'class_name' => '5 Bestari',
        'parent_name' => 'Ibu Gateway',
        'parent_phone' => $phone,
        'parent_email' => strtolower($familyCode).'@example.test',
        'billing_year' => now()->year,
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => $phone,
        'email' => strtolower($familyCode).'@example.test',
        'name' => 'Ibu Gateway',
        'email_verified_at' => now(),
    ]);

    return [$billing, $parent];
}

function gatewayBillingSession(FamilyBilling $billing): array
{
    return [
        'parent_child_selection_completed' => true,
        'parent_selected_family_billing_id' => $billing->id,
    ];
}

it('omits duitnow qr payload fields when qr is disabled', function () {
    PaymentGatewaySetting::query()->create([
        'enable_fpx' => true,
        'enable_duitnow_qr' => false,
        'charge_duitnow_qr_to_customer' => true,
    ]);

    [$billing, $parent] = createGatewayTestFamily('SSP-GATEWAY-FULL');

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('createBill')
        ->once()
        ->withArgs(function (array $payload): bool {
            return ($payload['billPaymentChannel'] ?? null) === '0'
                && ! array_key_exists('enableDuitNowQR', $payload)
                && ! array_key_exists('chargeDuitNowQR', $payload);
        })
        ->andReturn('BILL-GATEWAY-FULL');
    $toyyib->shouldReceive('paymentUrl')
        ->once()
        ->with('BILL-GATEWAY-FULL')
        ->andReturn('https://toyyibpay.com/BILL-GATEWAY-FULL');
    $this->app->instance(ToyyibPayService::class, $toyyib);

    $response = $this
        ->actingAs($parent)
        ->withSession(gatewayBillingSession($billing))
        ->post(route('parent.payments.create', $billing), [
            'payer_name' => 'Ibu Gateway',
            'payer_email' => strtolower($billing->family_code).'@example.test',
            'payer_phone' => $parent->phone,
            'donation_preset' => 0,
            'donation_custom' => 0,
            'donation_intention' => '',
        ]);

    $response->assertRedirect('https://toyyibpay.com/BILL-GATEWAY-FULL');
});

it('includes duitnow qr payload fields when qr is enabled for installment bills', function () {
    PaymentGatewaySetting::query()->create([
        'enable_fpx' => true,
        'enable_duitnow_qr' => true,
        'charge_duitnow_qr_to_customer' => true,
    ]);

    [$billing, $parent] = createGatewayTestFamily('SSP-GATEWAY-INSTALL');
    $plan = app(FamilyPaymentPlanService::class)->createPlan($billing, FamilyPaymentPlan::PLAN_TWO_TIMES);
    $installment = $plan->installments()->where('installment_no', 1)->firstOrFail();

    $toyyib = \Mockery::mock(ToyyibPayService::class);
    $toyyib->shouldReceive('createBill')
        ->once()
        ->withArgs(function (array $payload): bool {
            return ($payload['billPaymentChannel'] ?? null) === '0'
                && ($payload['enableDuitNowQR'] ?? null) === '1'
                && ($payload['chargeDuitNowQR'] ?? null) === '1';
        })
        ->andReturn('BILL-GATEWAY-INSTALL');
    $toyyib->shouldReceive('paymentUrl')
        ->once()
        ->with('BILL-GATEWAY-INSTALL')
        ->andReturn('https://toyyibpay.com/BILL-GATEWAY-INSTALL');
    $this->app->instance(ToyyibPayService::class, $toyyib);

    $response = $this
        ->actingAs($parent)
        ->withSession(gatewayBillingSession($billing))
        ->post(route('parent.payments.installments.pay', $installment), [
            'payer_name' => 'Ibu Gateway',
            'payer_email' => strtolower($billing->family_code).'@example.test',
            'payer_phone' => $parent->phone,
        ]);

    $response->assertRedirect('https://toyyibpay.com/BILL-GATEWAY-INSTALL');
});

it('prevents system admin from disabling both fpx and duitnow qr', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $setting = PaymentGatewaySetting::query()->create([
        'enable_fpx' => true,
        'enable_duitnow_qr' => false,
        'charge_duitnow_qr_to_customer' => true,
        'updated_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post(route('system.payment-gateway-settings.save'), [
        'enable_fpx' => '0',
        'enable_duitnow_qr' => '0',
    ]);

    $response->assertSessionHasErrors('enable_fpx');

    expect($setting->fresh()->enable_fpx)->toBeTrue();
    expect($setting->fresh()->enable_duitnow_qr)->toBeFalse();
});

it('shows the correct parent payment notice for each enabled gateway combination', function () {
    [$billing, $parent] = createGatewayTestFamily('SSP-GATEWAY-NOTICE');

    PaymentGatewaySetting::query()->create([
        'enable_fpx' => true,
        'enable_duitnow_qr' => true,
        'charge_duitnow_qr_to_customer' => true,
    ]);

    $this->actingAs($parent)
        ->withSession(gatewayBillingSession($billing))
        ->get(route('parent.payments.review', $billing))
        ->assertOk()
        ->assertSee('Pembayaran boleh dibuat melalui FPX / Internet Banking atau DuitNow QR. Caj perkhidmatan hanya terpakai untuk DuitNow QR sahaja: 1% atau minimum RM1.00, yang mana lebih tinggi. Caj ini akan dipaparkan di halaman ToyyibPay sebelum bayaran disahkan.')
        ->assertDontSee('Pembayaran akan diteruskan melalui FPX / Internet Banking di halaman ToyyibPay.');

    PaymentGatewaySetting::query()->delete();
    PaymentGatewaySetting::query()->create([
        'enable_fpx' => true,
        'enable_duitnow_qr' => false,
        'charge_duitnow_qr_to_customer' => true,
    ]);

    $this->actingAs($parent)
        ->withSession(gatewayBillingSession($billing))
        ->get(route('parent.payments.review', $billing))
        ->assertOk()
        ->assertSee('Pembayaran akan diteruskan melalui FPX / Internet Banking di halaman ToyyibPay.')
        ->assertDontSee('1% atau minimum RM1.00, yang mana lebih tinggi.');

    PaymentGatewaySetting::query()->delete();
    PaymentGatewaySetting::query()->create([
        'enable_fpx' => false,
        'enable_duitnow_qr' => true,
        'charge_duitnow_qr_to_customer' => true,
    ]);

    $this->actingAs($parent)
        ->withSession(gatewayBillingSession($billing))
        ->get(route('parent.payments.review', $billing))
        ->assertOk()
        ->assertSee('Pembayaran akan diteruskan melalui DuitNow QR. Caj perkhidmatan DuitNow QR ialah 1% atau minimum RM1.00, yang mana lebih tinggi. Caj ini akan dipaparkan di halaman ToyyibPay sebelum bayaran disahkan.')
        ->assertDontSee('FPX / Internet Banking di halaman ToyyibPay.');
});
