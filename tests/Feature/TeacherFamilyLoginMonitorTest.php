<?php

use App\Models\FamilyBilling;
use App\Models\FamilyBillingPhone;
use App\Models\ParentLoginAudit;
use App\Models\ParentLoginOtp;
use App\Models\User;

it('allows teacher roles to view family login monitor with aggregated data', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $family = FamilyBilling::query()->create([
        'family_code' => 'SSP-M001',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    FamilyBillingPhone::query()->create([
        'family_billing_id' => $family->id,
        'phone' => '0136544001',
        'normalized_phone' => '60136544001',
    ]);

    ParentLoginAudit::query()->create([
        'user_id' => null,
        'phone' => '0136544001',
        'normalized_phone' => '60136544001',
        'logged_in_at' => now()->subHour(),
    ]);

    ParentLoginAudit::query()->create([
        'user_id' => null,
        'phone' => '0136544001',
        'normalized_phone' => '60136544001',
        'logged_in_at' => now(),
    ]);

    ParentLoginOtp::query()->create([
        'user_id' => null,
        'phone' => '0136544001',
        'code_hash' => 'hash',
        'channel' => 'whatsapp',
        'expires_at' => now()->addMinutes(5),
        'used_at' => now()->subMinute(),
        'attempts' => 1,
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.family-login-monitor'));

    $response->assertOk();
    $response->assertSee('SSP-M001');
    $response->assertSee('0136544001');
    $response->assertSee('2');
    $response->assertSee('Completed');
    $response->assertSee('Yes');
});

it('shows expired tac status for families stuck before login', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $family = FamilyBilling::query()->create([
        'family_code' => 'SSP-M002',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    FamilyBillingPhone::query()->create([
        'family_billing_id' => $family->id,
        'phone' => '0168899551',
        'normalized_phone' => '60168899551',
    ]);

    ParentLoginOtp::query()->create([
        'user_id' => null,
        'phone' => '0168899551',
        'code_hash' => 'hash',
        'channel' => 'whatsapp',
        'expires_at' => now()->subMinutes(10),
        'used_at' => null,
        'attempts' => 2,
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.family-login-monitor'));

    $response->assertOk();
    $response->assertSee('SSP-M002');
    $response->assertSee('Expired TAC (Stuck)');
});

it('filters tac status to show stuck families only', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $stuckFamily = FamilyBilling::query()->create([
        'family_code' => 'SSP-STUCK',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    FamilyBillingPhone::query()->create([
        'family_billing_id' => $stuckFamily->id,
        'phone' => '0191111222',
        'normalized_phone' => '60191111222',
    ]);

    ParentLoginOtp::query()->create([
        'user_id' => null,
        'phone' => '0191111222',
        'code_hash' => 'hash',
        'channel' => 'whatsapp',
        'expires_at' => now()->subMinutes(3),
        'used_at' => null,
        'attempts' => 1,
    ]);

    $completedFamily = FamilyBilling::query()->create([
        'family_code' => 'SSP-DONE',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    FamilyBillingPhone::query()->create([
        'family_billing_id' => $completedFamily->id,
        'phone' => '0193333444',
        'normalized_phone' => '60193333444',
    ]);

    ParentLoginOtp::query()->create([
        'user_id' => null,
        'phone' => '0193333444',
        'code_hash' => 'hash',
        'channel' => 'whatsapp',
        'expires_at' => now()->addMinutes(5),
        'used_at' => now()->subMinute(),
        'attempts' => 1,
    ]);

    ParentLoginAudit::query()->create([
        'user_id' => null,
        'phone' => '0193333444',
        'normalized_phone' => '60193333444',
        'logged_in_at' => now(),
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.family-login-monitor', ['tac_status' => 'stuck']));

    $response->assertOk();
    $response->assertSee('SSP-STUCK');
    $response->assertDontSee('SSP-DONE');
});

it('blocks parent role from viewing family login monitor', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($parent)->get(route('teacher.family-login-monitor'));

    $response->assertForbidden();
});