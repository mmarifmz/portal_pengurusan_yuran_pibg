<?php

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;

test('public parent search page is accessible', function () {
    $response = $this->get(route('parent.search'));

    $response->assertOk();
});

test('teacher dashboard requires authentication', function () {
    $response = $this->get(route('teacher.dashboard'));

    $response->assertRedirect(route('login'));
});

test('teacher dashboard returns forbidden for parent role', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
    ]);

    $response = $this->actingAs($parent)->get(route('teacher.dashboard'));

    $response->assertForbidden();
});

test('pta dashboard is accessible for pta role', function () {
    $pta = User::factory()->create([
        'role' => 'pta',
    ]);

    $response = $this->actingAs($pta)->get(route('pta.dashboard'));

    $response->assertOk();
});

test('parent dashboard returns forbidden for teacher role', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
    ]);

    $response = $this->actingAs($teacher)->get(route('parent.dashboard'));

    $response->assertForbidden();
});

test('parent dashboard lists children and family billing for matching parent phone', function () {
    $parent = User::factory()->create([
        'email' => 'parent@example.com',
        'phone' => '0123456789',
        'role' => 'parent',
    ]);

    Student::query()->create([
        'student_no' => '1A-0001',
        'family_code' => 'FAM-T1',
        'full_name' => 'Nur Aina',
        'class_name' => '1 Amanah',
        'parent_name' => 'Puan Salmah',
        'parent_phone' => '0123456789',
        'parent_email' => 'parent@example.com',
        'total_fee' => 100,
        'paid_amount' => 0,
        'status' => 'active',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'FAM-T1',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 30,
        'status' => 'partial',
    ]);

    $response = $this->actingAs($parent)->get(route('parent.dashboard'));

    $response->assertOk();
    $response->assertSee('NUR AINA');
    $response->assertSee('FAM-T1');
});

test('user with both parent and teacher roles can access both dashboards', function () {
    $user = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
    ]);
    $user->assignRole('teacher');

    Student::query()->create([
        'student_no' => '1B-0002',
        'family_code' => 'FAM-BOTH',
        'full_name' => 'Aisyah Humaira',
        'class_name' => '1 Bestari',
        'parent_name' => 'Pn Role Dual',
        'parent_phone' => '0123456789',
        'parent_email' => 'dual@example.test',
        'total_fee' => 100,
        'paid_amount' => 50,
        'status' => 'active',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'FAM-BOTH',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 50,
        'status' => 'partial',
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Parent Portal')
        ->assertSee('Teacher Space');
    $this->actingAs($user)->get(route('parent.dashboard'))->assertOk();
    $this->actingAs($user)->get(route('teacher.dashboard'))->assertOk();
});

test('public search falls back to name/class when contact phone is new and unregistered', function () {
    FamilyBilling::query()->create([
        'family_code' => 'FAM-NEWPHONE',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    Student::query()->create([
        'student_no' => '2B-1234',
        'family_code' => 'FAM-NEWPHONE',
        'full_name' => 'AYRA SOFEA',
        'class_name' => '2 Bestari',
        'parent_name' => 'Puan Laila',
        'parent_phone' => '0198881111',
        'status' => 'active',
    ]);

    $response = $this->get(route('parent.search', [
        'student_keyword' => 'ayra',
        'contact' => '0146364001',
    ]));

    $response->assertOk();
    $response->assertSee('FAM-NEWPHONE');
    $response->assertSee('belum berdaftar');
});
