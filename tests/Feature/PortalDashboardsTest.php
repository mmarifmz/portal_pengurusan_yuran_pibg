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
    $response->assertSee('Nur Aina');
    $response->assertSee('FAM-T1');
});