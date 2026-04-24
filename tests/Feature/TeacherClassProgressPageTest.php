<?php

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;

it('allows teacher roles to access class progress page', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Angsana',
        'phone' => '0123001000',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    FamilyBilling::query()->create([
        'family_code' => 'SSP-CPG1',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'CPG-0001',
        'family_code' => 'SSP-CPG1',
        'full_name' => 'Aina Sofea',
        'class_name' => '1 Angsana',
        'parent_name' => 'Puan Niza',
        'parent_phone' => '0123456789',
        'status' => 'active',
        'billing_year' => $billingYear,
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.class-progress'));

    $response->assertOk();
    $response->assertSee('Progress Bayaran Yuran Mengikut Kelas');
    $response->assertSee('Tapis Tahun');
    $response->assertSee('Send to class teacher (Whatsapp)');
    $response->assertSee('Murid belum menjelaskan Yuran');
    $response->assertSee('Murid telah menjelaskan Yuran');
});

it('blocks parent from class progress page', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($parent)->get(route('teacher.class-progress'));

    $response->assertForbidden();
});