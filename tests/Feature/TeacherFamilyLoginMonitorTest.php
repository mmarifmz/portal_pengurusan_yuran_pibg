<?php

use App\Models\FamilyBilling;
use App\Models\FamilyBillingPhone;
use App\Models\ParentLoginAudit;
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

    $response = $this->actingAs($teacher)->get(route('teacher.family-login-monitor'));

    $response->assertOk();
    $response->assertSee('SSP-M001');
    $response->assertSee('0136544001');
    $response->assertSee('2');
    $response->assertSee('Yes');
});

it('blocks parent role from viewing family login monitor', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($parent)->get(route('teacher.family-login-monitor'));

    $response->assertForbidden();
});