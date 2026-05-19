<?php

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('allows teacher roles to access class progress page', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Angsana',
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

    User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Alamanda',
        'phone' => '0123001001',
        'email_verified_at' => now(),
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'SSP-CPG3',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    Student::query()->create([
        'student_no' => 'CPG-0003',
        'family_code' => 'SSP-CPG3',
        'full_name' => 'Danish Irfan',
        'class_name' => '1 Alamanda',
        'parent_name' => 'Encik Firdaus',
        'parent_phone' => '0191234567',
        'status' => 'active',
        'billing_year' => $billingYear,
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.class-progress'));

    $response->assertOk();
    $response->assertSee('Leaderboard Bayaran Mengikut Kelas');
    $response->assertSee('Tapis Tahun');
    $response->assertSee('Kelas Saya');
    $response->assertSee('Senarai Kelas Lain');
    $response->assertSeeInOrder(['Kelas Saya', '1 Angsana', 'Senarai Kelas Lain', '1 Alamanda']);
    $response->assertDontSee('Blast WhatsApp Report to All Class Teachers');
    $response->assertDontSee('WhatsApp Guru');
    $response->assertDontSee('View WhatsApp Queue');
});

it('allows system admin to see whatsapp actions on class progress page', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Angsana',
        'phone' => '0123001000',
        'email_verified_at' => now(),
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'SSP-CPG2',
        'billing_year' => (int) now()->year,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'CPG-0002',
        'family_code' => 'SSP-CPG2',
        'full_name' => 'Danish Irfan',
        'class_name' => '1 Angsana',
        'parent_name' => 'Puan Niza',
        'parent_phone' => '0123456789',
        'status' => 'active',
        'billing_year' => (int) now()->year,
    ]);

    $response = $this->actingAs($admin)->get(route('teacher.class-progress'));

    $response->assertOk();
    $response->assertSee('Blast WhatsApp Report to All Class Teachers');
    $response->assertSee('WhatsApp Guru');
    $response->assertSee('View WhatsApp Queue');
});

it('blocks parent from class progress page', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($parent)->get(route('teacher.class-progress'));

    $response->assertForbidden();
});
