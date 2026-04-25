<?php

use App\Models\Student;
use App\Models\User;

it('filters full student directory by selected social tag', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    Student::query()->create([
        'student_no' => 'RSF-001',
        'family_code' => 'SSP-RSF1',
        'full_name' => 'Aina B40',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'is_b40' => true,
        'is_kwap' => false,
        'is_rmt' => false,
    ]);

    Student::query()->create([
        'student_no' => 'RSF-002',
        'family_code' => 'SSP-RSF2',
        'full_name' => 'Hakim Bukan B40',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'is_b40' => false,
        'is_kwap' => true,
        'is_rmt' => false,
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.records', [
        'social_tag' => 'is_b40',
    ]));

    $response->assertOk();
    $response->assertSee('By social tag');
    $response->assertSee('AINA B40');
    $response->assertDontSee('HAKIM BUKAN B40');
});
