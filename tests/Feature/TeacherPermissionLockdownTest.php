<?php

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('hides admin billing actions from teacher-facing record pages', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($teacher)
        ->get(route('teacher.records'))
        ->assertOk()
        ->assertDontSee('Setup/Sync RM100 Family Billing')
        ->assertDontSee('Sync Parent Profile From Payments');

    $this->actingAs($teacher)
        ->get(route('students.family.list'))
        ->assertOk()
        ->assertDontSee('Generate family billing')
        ->assertDontSee('Tambah murid');
});

it('forbids teacher from billing sync and other admin-only routes', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($teacher)
        ->post(route('billing.setup.current-year'), ['billing_year' => now()->year])
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('system.backups.index'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('students.import.form'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('super-teacher.teachers.index'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('admin.whatsapp-queue.index'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->post(route('admin.classes.whatsapp-batch-queue'), ['class_names' => ['1 Angsana']])
        ->assertForbidden();
});

it('keeps teacher class progress access read only', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Angsana',
        'email_verified_at' => now(),
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'LOCK-001',
        'billing_year' => (int) now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'LOCK-001',
        'family_code' => 'LOCK-001',
        'full_name' => 'Read Only Teacher Student',
        'class_name' => '1 Angsana',
        'parent_name' => 'Puan Read Only',
        'parent_phone' => '0123456789',
        'billing_year' => (int) now()->year,
        'status' => 'active',
    ]);

    $this->actingAs($teacher)
        ->get(route('teacher.class-progress'))
        ->assertOk()
        ->assertSee('Class Progress')
        ->assertDontSee('Blast WhatsApp Report to All Class Teachers')
        ->assertDontSee('WhatsApp Guru');

    $this->actingAs($teacher)
        ->getJson(route('teacher.class-progress.details', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->assertOk()
        ->assertJsonPath('summary.class_name', '1 Angsana');
});

it('keeps super admin access to the restricted admin actions', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('teacher.records'))
        ->assertOk()
        ->assertSee('Setup/Sync RM100 Family Billing');

    $this->actingAs($admin)
        ->post(route('billing.setup.current-year'), ['billing_year' => now()->year])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('students.import.form'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('super-teacher.teachers.index'))
        ->assertOk();
});
