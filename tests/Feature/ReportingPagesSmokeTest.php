<?php

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;

function seedReportingSmokeData(): void
{
    FamilyBilling::query()->create([
        'family_code' => 'SSP-SMOKE-001',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'SMOKE-001',
        'family_code' => 'SSP-SMOKE-001',
        'full_name' => 'Murid Smoke Test',
        'class_name' => '5 Bestari',
        'parent_name' => 'Ibu Smoke Test',
        'parent_phone' => '0123000001',
        'parent_email' => 'smoke@example.test',
        'billing_year' => now()->year,
        'status' => 'active',
    ]);
}

it('loads the main reporting pages for system admin without runtime errors', function () {
    seedReportingSmokeData();

    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($admin)->get(route('teacher.finance-accounting'))
        ->assertOk()
        ->assertSee('Finance Accounting Dashboard');

    $this->actingAs($admin)->get(route('teacher.class-progress'))
        ->assertOk();

    $this->actingAs($admin)->get(route('teacher.records'))
        ->assertOk();
});
