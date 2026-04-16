<?php

use App\Models\FamilyBilling;
use App\Models\Student;

it('imports legacy family billings using student code, family code plus name, and unique name matching', function () {
    Student::query()->create([
        'student_no' => 'SSP66365',
        'family_code' => 'SSP-0001',
        'ssp_student_id' => 'SSP66365',
        'full_name' => 'KARIMA HAURA ZUHDA BINTI MOHD HELMI',
        'class_name' => '6 ALAMANDA',
        'billing_year' => 2026,
        'status' => 'active',
        'total_fee' => 0,
        'paid_amount' => 0,
        'annual_fee' => 100,
    ]);

    Student::query()->create([
        'student_no' => 'SSP12345',
        'family_code' => 'SSP-0002',
        'ssp_student_id' => 'SSP12345',
        'full_name' => 'ARTHUR ARSHIVANRAJ SIVARAJ',
        'class_name' => '6 AKASIA',
        'billing_year' => 2026,
        'status' => 'active',
        'total_fee' => 0,
        'paid_amount' => 0,
        'annual_fee' => 100,
    ]);

    Student::query()->create([
        'student_no' => 'SSP99999',
        'family_code' => 'SSP-0300',
        'ssp_student_id' => 'SSP99999',
        'full_name' => 'UNIQUE CHILD NAME',
        'class_name' => '3 AKASIA',
        'billing_year' => 2026,
        'status' => 'active',
        'total_fee' => 0,
        'paid_amount' => 0,
        'annual_fee' => 100,
    ]);

    $csv = <<<'CSV'
family_id,student_name,student_code,class_name,payment_status,amount_due,amount_paid,payment_reference,paid_at
F888,WRONG FAMILY BUT SAME CODE,SSP66365,6 ALAMANDA,paid,100.00,100.00,TP-001,2025-07-01 10:00:00
F002,ARTHUR ARSHIVANRAJ SIVARAJ,,5 AKASIA,paid,100.00,100.00,TP-002,2025-06-22 07:24:46
F777,UNIQUE CHILD NAME,,2 AKASIA,partial,100.00,60.00,TP-003,2025-05-15 08:00:00
CSV;

    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'legacy-families-test.csv';
    file_put_contents($path, $csv);

    $this->artisan('billing:import-legacy-families', [
        'path' => $path,
        '--legacy-year' => 2025,
        '--current-year' => 2026,
        '--school-code' => 'SSP',
    ])
        ->expectsOutputToContain('Processed rows: 3')
        ->expectsOutputToContain('Matched by student code: 1')
        ->expectsOutputToContain('Matched by family code + name: 1')
        ->expectsOutputToContain('Matched by unique name only: 1')
        ->assertSuccessful();

    $this->assertDatabaseHas('family_billings', [
        'family_code' => 'SSP-0001',
        'billing_year' => 2025,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('family_billings', [
        'family_code' => 'SSP-0002',
        'billing_year' => 2025,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('family_billings', [
        'family_code' => 'SSP-0300',
        'billing_year' => 2025,
        'paid_amount' => 60,
        'status' => 'partial',
    ]);

    @unlink($path);
});

it('reports ambiguous unique-name matches without importing them', function () {
    Student::query()->create([
        'student_no' => 'SSP10001',
        'family_code' => 'SSP-0004',
        'ssp_student_id' => 'SSP10001',
        'full_name' => 'DUPLICATE NAME',
        'class_name' => '2 AZALEA',
        'billing_year' => 2026,
        'status' => 'active',
        'total_fee' => 0,
        'paid_amount' => 0,
        'annual_fee' => 100,
    ]);

    Student::query()->create([
        'student_no' => 'SSP10002',
        'family_code' => 'SSP-0005',
        'ssp_student_id' => 'SSP10002',
        'full_name' => 'DUPLICATE NAME',
        'class_name' => '4 AKASIA',
        'billing_year' => 2026,
        'status' => 'active',
        'total_fee' => 0,
        'paid_amount' => 0,
        'annual_fee' => 100,
    ]);

    $csv = <<<'CSV'
family_id,student_name,class_name,payment_status,amount_due,amount_paid
F009,DUPLICATE NAME,3 AKASIA,paid,100.00,100.00
CSV;

    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'legacy-families-ambiguous-test.csv';
    file_put_contents($path, $csv);

    $this->artisan('billing:import-legacy-families', [
        'path' => $path,
        '--legacy-year' => 2025,
        '--current-year' => 2026,
        '--school-code' => 'SSP',
    ])
        ->expectsOutputToContain('Ambiguous rows: 1')
        ->assertSuccessful();

    expect(FamilyBilling::query()->count())->toBe(0);

    @unlink($path);
});
