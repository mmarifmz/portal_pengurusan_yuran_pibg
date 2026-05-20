<?php

use App\Models\FamilyBilling;
use App\Models\ParentStudentLink;
use App\Models\Student;
use App\Models\User;
use App\Models\UserChangeAudit;

it('allows system admin to autosave parent access status and audit the change', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->patchJson(route('teacher.parent-management.settings.autosave', $parent), [
        'is_active' => false,
        'access_block_reason' => 'Blocked after duplicate access report.',
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'saved']);

    expect($parent->fresh()->is_active)->toBeFalse();
    expect($parent->fresh()->access_block_reason)->toBe('Blocked after duplicate access report.');
    expect(UserChangeAudit::query()->where('affected_user_id', $parent->id)->where('field_changed', 'is_active')->exists())->toBeTrue();
});

it('allows system admin to autosave dual role assignment for a parent user', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
    ]);

    $response = $this->actingAs($admin)->patchJson(route('teacher.parent-management.settings.autosave', $parent), [
        'roles' => ['parent', 'teacher'],
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'saved']);

    expect($parent->fresh()->hasRole('parent'))->toBeTrue();
    expect($parent->fresh()->hasRole('teacher'))->toBeTrue();
    expect(UserChangeAudit::query()->where('affected_user_id', $parent->id)->where('field_changed', 'roles')->exists())->toBeTrue();
});

it('forbids admin alias from parent management autosave', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patchJson(route('teacher.parent-management.settings.autosave', $parent), [
            'is_active' => false,
        ])
        ->assertForbidden();
});

it('forbids parent from accessing other family billing records', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
    ]);

    $ownedStudent = Student::query()->create([
        'student_no' => '1A-0001',
        'family_code' => 'FAM-OWN',
        'full_name' => 'Anak Sendiri',
        'class_name' => '1 AMANAH',
        'parent_name' => 'Parent',
        'parent_phone' => '0123456789',
        'status' => 'active',
        'billing_year' => now()->year,
    ]);

    ParentStudentLink::query()->create([
        'user_id' => $parent->id,
        'student_id' => $ownedStudent->id,
        'relationship_type' => 'guardian',
        'linked_at' => now(),
    ]);

    $ownedBilling = FamilyBilling::query()->create([
        'family_code' => 'FAM-OWN',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $otherBilling = FamilyBilling::query()->create([
        'family_code' => 'FAM-OTHER',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $this->actingAs($parent)
        ->withSession([
            'parent_child_selection_completed' => true,
            'parent_selected_family_billing_id' => $otherBilling->id,
        ])
        ->get(route('parent.payments.checkout', $otherBilling))
        ->assertForbidden();

    $this->actingAs($parent)
        ->withSession([
            'parent_child_selection_completed' => true,
            'parent_selected_family_billing_id' => $ownedBilling->id,
        ])
        ->get(route('parent.payments.checkout', $ownedBilling))
        ->assertOk();
});

it('forbids teacher from parent payment history', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
    ]);

    $this->actingAs($teacher)
        ->get(route('parent.payments.history'))
        ->assertForbidden();
});
