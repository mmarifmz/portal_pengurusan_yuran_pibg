<?php

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;
use App\Services\PaymentReportingService;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('allows system admin to change a student class and records the history', function () {
    $admin = User::factory()->create([
        'name' => 'School System Admin',
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    FamilyBilling::query()->create([
        'family_code' => 'SSP-CLASS-001',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $student = Student::query()->create([
        'student_no' => 'CLASS-001',
        'family_code' => 'SSP-CLASS-001',
        'full_name' => 'Nur Class Change',
        'class_name' => '3 AKASIA',
        'billing_year' => $billingYear,
        'status' => Student::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($admin)->patch(route('teacher.records.students.class.update', $student), [
        'class_student_id' => $student->id,
        'class_name' => '  4   BAKAWALI ',
        'reason' => 'Pertukaran kelas diluluskan pihak sekolah.',
    ]);

    $response->assertRedirect(route('teacher.records.family', ['familyCode' => 'SSP-CLASS-001']).'#student-class-'.$student->id);

    expect($student->refresh()->getRawOriginal('class_name'))->toBe('4 BAKAWALI');

    $this->assertDatabaseHas('student_class_changes', [
        'student_id' => $student->id,
        'old_class_name' => '3 AKASIA',
        'new_class_name' => '4 BAKAWALI',
        'reason' => 'Pertukaran kelas diluluskan pihak sekolah.',
        'changed_by_user_id' => $admin->id,
    ]);

    $classLeaderboard = app(PaymentReportingService::class)->classLeaderboard($billingYear);

    expect($classLeaderboard->pluck('class_name')->all())
        ->toContain('4 BAKAWALI')
        ->not->toContain('3 AKASIA');

    $this->actingAs($admin)
        ->get(route('teacher.records.family', ['familyCode' => 'SSP-CLASS-001']))
        ->assertOk()
        ->assertSee('Pertukaran Kelas Murid')
        ->assertSee('Sejarah Pertukaran Kelas')
        ->assertSee('3 AKASIA')
        ->assertSee('4 BAKAWALI')
        ->assertSee('Pertukaran kelas diluluskan pihak sekolah.')
        ->assertSee('SCHOOL SYSTEM ADMIN');
});

it('rejects a class update when the new class is unchanged', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'CLASS-002',
        'family_code' => 'SSP-CLASS-002',
        'full_name' => 'Class Unchanged',
        'class_name' => '5 CEMPAKA',
        'billing_year' => (int) now()->year,
        'status' => Student::STATUS_ACTIVE,
    ]);

    $this->actingAs($admin)
        ->patch(route('teacher.records.students.class.update', $student), [
            'class_student_id' => $student->id,
            'class_name' => '5 CEMPAKA',
        ])
        ->assertRedirect(route('teacher.records.family', ['familyCode' => 'SSP-CLASS-002']).'#student-class-'.$student->id)
        ->assertSessionHasErrors(['class_name']);

    $this->assertDatabaseMissing('student_class_changes', [
        'student_id' => $student->id,
    ]);
});

it('forbids non system admin users from changing a student class', function (string $role) {
    $user = User::factory()->create([
        'role' => $role,
        'email_verified_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'CLASS-'.$role,
        'family_code' => 'SSP-CLASS-'.$role,
        'full_name' => 'Restricted Class Change',
        'class_name' => '2 AKASIA',
        'billing_year' => (int) now()->year,
        'status' => Student::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
        ->patch(route('teacher.records.students.class.update', $student), [
            'class_name' => '2 BAKAWALI',
        ])
        ->assertForbidden();

    expect($student->refresh()->getRawOriginal('class_name'))->toBe('2 AKASIA');
})->with(['teacher', 'super_teacher']);
