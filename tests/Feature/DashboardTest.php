<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\ParentLoginAudit;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard year selector excludes future years', function () {
    $currentYear = (int) now()->year;
    $futureYear = $currentYear + 1;

    FamilyBilling::query()->create([
        'family_code' => 'FAM-CURR',
        'billing_year' => $currentYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'FAM-FUTURE',
        'billing_year' => $futureYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $user = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('name="dashboard_year"', false);
    $response->assertSee('value="'.$currentYear.'"', false);
    $response->assertDontSee('value="'.$futureYear.'"', false);
});

test('school calendar year selector excludes future years', function () {
    $currentYear = (int) now()->year;
    $futureYear = $currentYear + 1;

    FamilyBilling::query()->create([
        'family_code' => 'CAL-CURR',
        'billing_year' => $currentYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'CAL-FUTURE',
        'billing_year' => $futureYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $user = User::factory()->create([
        'role' => 'parent',
    ]);

    $response = $this->actingAs($user)->get(route('school-calendar'));

    $response->assertOk();
    $response->assertSee('name="dashboard_year"', false);
    $response->assertSee('value="'.$currentYear.'"', false);
    $response->assertDontSee('value="'.$futureYear.'"', false);
});

test('school calendar exposes daily paid and parent activity counts for staff', function () {
    $date = now()->setDate((int) now()->year, 6, 3)->startOfDay();

    $billing = FamilyBilling::query()->create([
        'family_code' => 'CAL-ACTIVITY-001',
        'billing_year' => $date->year,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'CAL-ACTIVITY-PAID-1',
        'amount' => 100,
        'status' => 'success',
        'paid_at' => $date->copy()->setTime(9, 0),
    ]);

    FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'CAL-ACTIVITY-PAID-2',
        'amount' => 10,
        'status' => 'success',
        'paid_at' => $date->copy()->setTime(10, 0),
    ]);

    ParentLoginAudit::query()->create([
        'phone' => '0123000002',
        'normalized_phone' => '60123000002',
        'action_type' => 'login',
        'access_status' => 'successful',
        'logged_in_at' => $date->copy()->setTime(8, 0),
        'occurred_at' => $date->copy()->setTime(8, 0),
    ]);

    ParentLoginAudit::query()->create([
        'phone' => '0123000003',
        'normalized_phone' => '60123000003',
        'action_type' => 'login',
        'access_status' => 'successful',
        'logged_in_at' => $date->copy()->setTime(8, 2),
        'occurred_at' => $date->copy()->setTime(8, 2),
    ]);

    ParentLoginAudit::query()->create([
        'phone' => '0123000002',
        'normalized_phone' => '60123000002',
        'action_type' => 'viewed_payment',
        'access_status' => 'successful',
        'logged_in_at' => $date->copy()->setTime(8, 5),
        'occurred_at' => $date->copy()->setTime(8, 5),
    ]);

    ParentLoginAudit::query()->create([
        'phone' => '0123000002',
        'normalized_phone' => '60123000002',
        'action_type' => 'viewed_receipt',
        'access_status' => 'successful',
        'logged_in_at' => $date->copy()->setTime(8, 7),
        'occurred_at' => $date->copy()->setTime(8, 7),
    ]);

    ParentLoginAudit::query()->create([
        'phone' => '0123000002',
        'normalized_phone' => '60123000002',
        'action_type' => 'clicked_pay_now',
        'access_status' => 'successful',
        'logged_in_at' => $date->copy()->setTime(8, 9),
        'occurred_at' => $date->copy()->setTime(8, 9),
    ]);

    $admin = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $response = $this->actingAs($admin)->get(route('school-calendar', [
        'dashboard_year' => $date->year,
    ]));

    $response->assertOk();
    $response->assertSee('const paidCountByDate = {"'.$date->toDateString().'":1};', false);
    $response->assertSee('const loginCountByDate = {"'.$date->toDateString().'":2};', false);
    $response->assertSee('const visitCountByDate = {"'.$date->toDateString().'":3};', false);
});

test('payment funnel billing year filter excludes future years', function () {
    $currentYear = (int) now()->year;
    $futureYear = $currentYear + 1;

    FamilyBilling::query()->create([
        'family_code' => 'FUN-CURR',
        'billing_year' => $currentYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'FUN-FUTURE',
        'billing_year' => $futureYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $user = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $response = $this->actingAs($user)->get(route('system.payment-funnel-monitor.index'));

    $response->assertOk();
    $response->assertSee((string) $currentYear);
    $response->assertDontSee((string) $futureYear);
});

test('teacher cannot access payment funnel monitor', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
    ]);

    $response = $this->actingAs($teacher)->get(route('system.payment-funnel-monitor.index'));

    $response->assertForbidden();
});

test('teacher cannot access finance accounting dashboard', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.finance-accounting'));

    $response->assertForbidden();
});
