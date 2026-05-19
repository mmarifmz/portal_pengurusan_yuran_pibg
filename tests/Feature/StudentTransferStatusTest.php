<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('allows super admin to mark a student as transferred and preserves payment records', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-TRF-001',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'TRF-001',
        'amount' => 100,
        'fee_amount_paid' => 100,
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'TRF-001',
        'family_code' => 'SSP-TRF-001',
        'full_name' => 'Nur Transfer Test',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($admin)->patch(route('teacher.records.students.status.update', $student), [
        'status' => Student::STATUS_TRANSFERRED,
        'transfer_note' => 'Berpindah ke sekolah lain.',
    ]);

    $response->assertRedirect(route('teacher.records.family', ['familyCode' => 'SSP-TRF-001']).'#student-status-'.$student->id);

    $student->refresh();

    expect($student->status)->toBe(Student::STATUS_TRANSFERRED);
    expect($student->transferred_at)->not->toBeNull();
    expect($student->transferred_by)->toBe($admin->id);
    expect($student->transfer_note)->toBe('Berpindah ke sekolah lain.');

    expect(FamilyBilling::query()->whereKey($billing->id)->exists())->toBeTrue();
    expect(FamilyPaymentTransaction::query()->whereKey($transaction->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('student_status_audits', [
        'student_id' => $student->id,
        'old_status' => Student::STATUS_ACTIVE,
        'new_status' => Student::STATUS_TRANSFERRED,
        'changed_by' => $admin->id,
    ]);
});

it('forbids teacher from marking a student as transferred', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'TRF-002',
        'family_code' => 'SSP-TRF-002',
        'full_name' => 'Teacher Cannot Update',
        'class_name' => '1 Angsana',
        'billing_year' => (int) now()->year,
        'status' => Student::STATUS_ACTIVE,
    ]);

    $this->actingAs($teacher)
        ->patch(route('teacher.records.students.status.update', $student), [
            'status' => Student::STATUS_TRANSFERRED,
        ])
        ->assertForbidden();
});

it('hides transferred students from the default student directory view', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    Student::query()->create([
        'student_no' => 'TRF-003-A',
        'family_code' => 'SSP-TRF-003A',
        'full_name' => 'Active Student Visible',
        'class_name' => '2 Aman',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_ACTIVE,
    ]);

    Student::query()->create([
        'student_no' => 'TRF-003-B',
        'family_code' => 'SSP-TRF-003B',
        'full_name' => 'Transferred Student Hidden',
        'class_name' => '2 Aman',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_TRANSFERRED,
    ]);

    $this->actingAs($admin)
        ->get(route('teacher.records'))
        ->assertOk()
        ->assertSee('ACTIVE STUDENT VISIBLE')
        ->assertDontSee('TRANSFERRED STUDENT HIDDEN');

    $this->actingAs($admin)
        ->get(route('teacher.records', ['include_transferred' => 1]))
        ->assertOk()
        ->assertSee('TRANSFERRED STUDENT HIDDEN')
        ->assertSee('Telah Berpindah');
});

it('excludes transferred students from class progress and keeps mixed families tracked', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Angsana',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    FamilyBilling::query()->create([
        'family_code' => 'SSP-TRF-ACTIVE',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'TRF-ACTIVE-1',
        'family_code' => 'SSP-TRF-ACTIVE',
        'full_name' => 'Active Child Kept',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_ACTIVE,
        'parent_name' => 'Puan A',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'SSP-TRF-ONLY',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'TRF-ONLY-1',
        'family_code' => 'SSP-TRF-ONLY',
        'full_name' => 'Transferred Only Child',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_TRANSFERRED,
        'parent_name' => 'Puan B',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'SSP-TRF-MIXED',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'TRF-MIXED-1',
        'family_code' => 'SSP-TRF-MIXED',
        'full_name' => 'Mixed Active Child',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_ACTIVE,
        'parent_name' => 'Puan C',
    ]);

    Student::query()->create([
        'student_no' => 'TRF-MIXED-2',
        'family_code' => 'SSP-TRF-MIXED',
        'full_name' => 'Mixed Transferred Child',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_TRANSFERRED,
        'parent_name' => 'Puan C',
    ]);

    $response = $this->actingAs($teacher)
        ->getJson(route('teacher.class-progress.details', ['class' => '1 Angsana']).'?billing_year='.$billingYear);

    $response->assertOk();
    $response->assertJsonPath('summary.total_families', 2);

    $unpaidEntries = collect($response->json('unpaid_entries'));

    expect($unpaidEntries->pluck('family_code')->sort()->values()->all())->toBe(['SSP-TRF-ACTIVE', 'SSP-TRF-MIXED']);
    expect($unpaidEntries->pluck('student_name_display')->implode(' | '))->toContain('MIXED ACTIVE CHILD');
    expect($unpaidEntries->pluck('student_name_display')->implode(' | '))->not->toContain('MIXED TRANSFERRED CHILD');
    expect($unpaidEntries->pluck('family_code')->all())->not->toContain('SSP-TRF-ONLY');
});

it('excludes transferred students from whatsapp class reports', function () {
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

    $billingYear = (int) now()->year;

    FamilyBilling::query()->create([
        'family_code' => 'SSP-TRF-WA-1',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'TRF-WA-1',
        'family_code' => 'SSP-TRF-WA-1',
        'full_name' => 'Paid Active Child',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_ACTIVE,
    ]);

    Student::query()->create([
        'student_no' => 'TRF-WA-2',
        'family_code' => 'SSP-TRF-WA-1',
        'full_name' => 'Paid Transferred Child',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_TRANSFERRED,
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'SSP-TRF-WA-2',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'TRF-WA-3',
        'family_code' => 'SSP-TRF-WA-2',
        'full_name' => 'Unpaid Transferred Child',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_TRANSFERRED,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.$billingYear);

    $response->assertOk();
    $response->assertJsonPath('class_stats.total_students', 1);
    $response->assertJsonPath('class_stats.paid_count', 1);
    $response->assertJsonPath('class_stats.unpaid_count', 0);

    $paidStudents = collect($response->json('paid_students'));
    $unpaidStudents = collect($response->json('unpaid_students'));

    expect($paidStudents->pluck('student_name')->all())->toBe(['PAID ACTIVE CHILD']);
    expect($paidStudents->pluck('student_name')->all())->not->toContain('PAID TRANSFERRED CHILD');
    expect($unpaidStudents)->toHaveCount(0);
});
