<?php

use App\Models\FamilyBilling;
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
