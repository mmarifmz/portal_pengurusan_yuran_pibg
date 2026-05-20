<?php

use App\Models\FamilyBilling;
use App\Models\ParentLoginAudit;
use App\Models\Student;
use App\Models\User;

it('records parent dashboard access activity', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
    ]);

    Student::query()->create([
        'student_no' => '1A-0001',
        'family_code' => 'FAM-T1',
        'full_name' => 'Nur Aina',
        'class_name' => '1 Amanah',
        'parent_name' => 'Puan Salmah',
        'parent_phone' => '0123456789',
        'status' => 'active',
        'billing_year' => now()->year,
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'FAM-T1',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 30,
        'status' => 'partial',
    ]);

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk();

    expect(ParentLoginAudit::query()
        ->where('user_id', $parent->id)
        ->where('action_type', 'viewed_dashboard')
        ->exists())->toBeTrue();
});

it('records parent space switching for dual role users', function () {
    $user = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
    ]);
    $user->assignRole('teacher');

    $response = $this->actingAs($user)->post(route('portal-space.switch'), [
        'space' => 'parent',
    ]);

    $response->assertRedirect(route('parent.dashboard'))
        ->assertSessionHas('active_portal_space', 'parent');
    expect(ParentLoginAudit::query()
        ->where('user_id', $user->id)
        ->where('action_type', 'parent_space_opened')
        ->exists())->toBeTrue();
});

it('records blocked parent otp access attempts', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0139906160',
        'is_active' => false,
    ]);

    $family = FamilyBilling::query()->create([
        'family_code' => 'FAM-BLOCK',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => '1A-9999',
        'family_code' => 'FAM-BLOCK',
        'full_name' => 'Blocked Child',
        'class_name' => '1 Amanah',
        'parent_name' => 'Blocked Parent',
        'parent_phone' => '0139906160',
        'status' => 'active',
        'billing_year' => now()->year,
    ]);

    $this->post(route('parent.login.request'), [
        'phone' => '0139906160',
        'family_billing_id' => $family->id,
    ])->assertSessionHasErrors('phone');

    expect(ParentLoginAudit::query()
        ->where('action_type', 'blocked_access')
        ->where('phone', '0139906160')
        ->exists())->toBeTrue();
});
