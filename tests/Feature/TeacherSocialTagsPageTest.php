<?php

use App\Models\SiteSetting;
use App\Models\Student;
use App\Models\User;

it('shows teacher social tag analytics with hashtag counts', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    SiteSetting::setMany([
        'social_tag_label_b40' => 'B40',
        'social_tag_label_kwap' => 'KWAP',
        'social_tag_label_rmt' => 'RMT',
    ]);

    $billingYear = (int) now()->year;

    Student::query()->create([
        'student_no' => 'STAG-001',
        'family_code' => 'SSP-TAG1',
        'full_name' => 'Nur Aisyah',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'is_b40' => true,
        'is_kwap' => false,
        'is_rmt' => true,
    ]);

    Student::query()->create([
        'student_no' => 'STAG-002',
        'family_code' => 'SSP-TAG2',
        'full_name' => 'Hakim',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'is_b40' => false,
        'is_kwap' => true,
        'is_rmt' => false,
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.social-tags.index', [
        'billing_year' => $billingYear,
    ]));

    $response->assertOk();
    $response->assertSee('Social Tags Analytics');
    $response->assertSee('#B40');
    $response->assertSee('#KWAP');
    $response->assertSee('#RMT');
    $response->assertSee('Tag Count by Class');
    $response->assertSee('1 Angsana');
});

it('blocks parent from teacher social tags page', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($parent)->get(route('teacher.social-tags.index'));

    $response->assertForbidden();
});