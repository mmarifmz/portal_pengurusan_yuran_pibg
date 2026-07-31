<?php

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('shows the next family code and the simplified two-column template', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    Student::query()->create([
        'student_no' => 'SSP61234',
        'family_code' => 'SSP-0042',
        'ssp_student_id' => 'SSP61234',
        'full_name' => 'Existing Student',
        'class_name' => '6 ALAMANDA',
        'billing_year' => now()->year,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('students.import.form'))
        ->assertOk()
        ->assertSee('SSP-0043')
        ->assertSee('Nama murid / Kelas')
        ->assertDontSee('Kod keluarga / Kelas / Nama murid')
        ->assertDontSee('name="school_code"', false);
});

it('imports names and classes while assigning sequential family codes', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    Student::query()->create([
        'student_no' => 'SSP61234',
        'family_code' => 'SSP-0042',
        'ssp_student_id' => 'SSP61234',
        'full_name' => 'Existing Student',
        'class_name' => '6 ALAMANDA',
        'billing_year' => now()->year,
        'status' => 'active',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'SSP-0044',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $this->actingAs($admin)
        ->post(route('students.import'), [
            'delimiter' => 'comma',
            'import_mode' => 'new',
            'bulk_rows' => implode("\n", [
                'NUR AINA BINTI AHMAD,1 ANGGERIK',
                'MUHAMMAD DANIAL BIN ZAKI,2 BAKAWALI',
            ]),
        ])
        ->assertRedirect(route('students.import.form'))
        ->assertSessionHas(
            'student_import_message',
            'Processed 2 lines. Added 2 students. Assigned family codes SSP-0045 to SSP-0046.'
        );

    $firstStudent = Student::query()
        ->where('full_name', 'NUR AINA BINTI AHMAD')
        ->firstOrFail();
    $secondStudent = Student::query()
        ->where('full_name', 'MUHAMMAD DANIAL BIN ZAKI')
        ->firstOrFail();

    expect($firstStudent->family_code)->toBe('SSP-0045')
        ->and($firstStudent->class_name)->toBe('1 ANGGERIK')
        ->and($firstStudent->student_no)->toStartWith('SSP1')
        ->and($secondStudent->family_code)->toBe('SSP-0046')
        ->and($secondStudent->class_name)->toBe('2 BAKAWALI')
        ->and($secondStudent->student_no)->toStartWith('SSP2');
});

it('adds siblings to an existing family without allocating another family code', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    Student::query()->create([
        'student_no' => 'SSP61234',
        'family_code' => 'SSP-0042',
        'ssp_student_id' => 'SSP61234',
        'full_name' => 'EXISTING SIBLING',
        'class_name' => '6 ALAMANDA',
        'billing_year' => now()->year,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('students.import'), [
            'delimiter' => 'pipe',
            'import_mode' => 'existing',
            'existing_family_code' => 'ssp-0042',
            'bulk_rows' => 'NEW SIBLING|1 ANGGERIK',
        ])
        ->assertRedirect(route('students.import.form'))
        ->assertSessionHas(
            'student_import_message',
            'Processed 1 lines. Added 1 students. Linked them to existing family SSP-0042.'
        );

    $newSibling = Student::query()
        ->where('full_name', 'NEW SIBLING')
        ->firstOrFail();

    expect($newSibling->family_code)->toBe('SSP-0042')
        ->and($newSibling->class_name)->toBe('1 ANGGERIK')
        ->and(Student::query()->where('family_code', 'SSP-0043')->exists())->toBeFalse();
});

it('rejects an unknown family code in sibling mode', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->from(route('students.import.form'))
        ->post(route('students.import'), [
            'delimiter' => 'comma',
            'import_mode' => 'existing',
            'existing_family_code' => 'SSP-9999',
            'bulk_rows' => 'NEW SIBLING,1 ANGGERIK',
        ])
        ->assertRedirect(route('students.import.form'))
        ->assertSessionHasErrors('existing_family_code');

    expect(Student::query()->where('full_name', 'NEW SIBLING')->exists())->toBeFalse();
});

it('does not create the same student twice within an existing family', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    Student::query()->create([
        'student_no' => 'SSP61234',
        'family_code' => 'SSP-0042',
        'ssp_student_id' => 'SSP61234',
        'full_name' => 'EXISTING SIBLING',
        'class_name' => '6 ALAMANDA',
        'billing_year' => now()->year,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('students.import'), [
            'delimiter' => 'comma',
            'import_mode' => 'existing',
            'existing_family_code' => 'SSP-0042',
            'bulk_rows' => 'EXISTING SIBLING,6 ALAMANDA',
        ])
        ->assertRedirect(route('students.import.form'))
        ->assertSessionHas(
            'student_import_message',
            'Processed 1 lines. Duplicates: SSP-0042 / EXISTING SIBLING'
        );

    expect(Student::query()
        ->where('family_code', 'SSP-0042')
        ->where('full_name', 'EXISTING SIBLING')
        ->count())->toBe(1);
});
