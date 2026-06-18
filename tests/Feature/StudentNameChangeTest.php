<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Models\User;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('allows community admin to update a student name with an audit record and keeps payments linked', function () {
    $admin = User::factory()->create([
        'name' => 'Community Admin',
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-NAME-001',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'NAME-001',
        'amount' => 100,
        'fee_amount_paid' => 100,
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'NAME-001',
        'family_code' => 'SSP-NAME-001',
        'full_name' => 'Nur Old Name',
        'class_name' => '3 Angsana',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($admin)->patch(route('teacher.records.students.name.update', $student), [
        'full_name' => 'nur new name',
        'reason' => 'Pembetulan ejaan nama dalam rekod sekolah.',
    ]);

    $response->assertRedirect(route('teacher.records.family', ['familyCode' => 'SSP-NAME-001']).'#student-info-'.$student->id);

    $student->refresh();

    expect($student->getRawOriginal('full_name'))->toBe('NUR NEW NAME');
    expect(FamilyBilling::query()->whereKey($billing->id)->exists())->toBeTrue();
    expect(FamilyPaymentTransaction::query()->whereKey($transaction->id)->value('family_billing_id'))->toBe($billing->id);

    $this->assertDatabaseHas('student_name_changes', [
        'student_id' => $student->id,
        'old_name' => 'NUR OLD NAME',
        'new_name' => 'NUR NEW NAME',
        'reason' => 'Pembetulan ejaan nama dalam rekod sekolah.',
        'changed_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('teacher.records.family', ['familyCode' => 'SSP-NAME-001']))
        ->assertOk()
        ->assertSee('Maklumat Murid')
        ->assertSee('History Perubahan Nama')
        ->assertSee('NUR OLD NAME')
        ->assertSee('NUR NEW NAME')
        ->assertSee('Pembetulan ejaan nama dalam rekod sekolah.');

    $this->actingAs($admin)
        ->get(route('teacher.records', ['student_name' => 'new name']))
        ->assertOk()
        ->assertSee('NUR NEW NAME')
        ->assertDontSee('NUR OLD NAME');
});

it('allows teacher admin to update a student name', function () {
    $teacherAdmin = User::factory()->create([
        'role' => 'super_teacher',
        'email_verified_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'NAME-002',
        'family_code' => 'SSP-NAME-002',
        'full_name' => 'Teacher Admin Old',
        'class_name' => '4 Angsana',
        'billing_year' => (int) now()->year,
        'status' => Student::STATUS_ACTIVE,
    ]);

    $this->actingAs($teacherAdmin)
        ->patch(route('teacher.records.students.name.update', $student), [
            'full_name' => 'Teacher Admin New',
            'reason' => 'Nama disahkan oleh pejabat sekolah.',
        ])
        ->assertRedirect(route('teacher.records.family', ['familyCode' => 'SSP-NAME-002']).'#student-info-'.$student->id);

    expect($student->refresh()->getRawOriginal('full_name'))->toBe('TEACHER ADMIN NEW');
});

it('requires valid student name and change reason before saving', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'NAME-003',
        'family_code' => 'SSP-NAME-003',
        'full_name' => 'Validation Old',
        'class_name' => '5 Angsana',
        'billing_year' => (int) now()->year,
        'status' => Student::STATUS_ACTIVE,
    ]);

    $this->actingAs($admin)
        ->from(route('teacher.records.family', ['familyCode' => 'SSP-NAME-003']))
        ->patch(route('teacher.records.students.name.update', $student), [
            'student_id' => $student->id,
            'full_name' => 'Al',
            'reason' => '',
        ])
        ->assertRedirect(route('teacher.records.family', ['familyCode' => 'SSP-NAME-003']))
        ->assertSessionHasErrors(['full_name', 'reason']);

    $this->assertDatabaseMissing('student_name_changes', [
        'student_id' => $student->id,
    ]);

    expect($student->refresh()->getRawOriginal('full_name'))->toBe('Validation Old');
});

it('forbids regular teacher from updating a student name', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'NAME-004',
        'family_code' => 'SSP-NAME-004',
        'full_name' => 'Teacher Cannot Rename',
        'class_name' => '6 Angsana',
        'billing_year' => (int) now()->year,
        'status' => Student::STATUS_ACTIVE,
    ]);

    $this->actingAs($teacher)
        ->patch(route('teacher.records.students.name.update', $student), [
            'full_name' => 'Teacher Tried Rename',
            'reason' => 'Should fail.',
        ])
        ->assertForbidden();

    expect($student->refresh()->getRawOriginal('full_name'))->toBe('Teacher Cannot Rename');
});
