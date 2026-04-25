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

it('bulk applies selected social tag to matched student family', function () {
    $this->withoutMiddleware();

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
        'student_no' => 'BULK-001',
        'family_code' => 'SSP-BULK1',
        'full_name' => 'Muhammad Zahir Iman',
        'class_name' => '1 Alamanda',
        'billing_year' => $billingYear,
        'is_b40' => false,
    ]);

    Student::query()->create([
        'student_no' => 'BULK-002',
        'family_code' => 'SSP-BULK1',
        'full_name' => 'Nur Dania',
        'class_name' => '3 Angsana',
        'billing_year' => $billingYear,
        'is_b40' => false,
    ]);

    Student::query()->create([
        'student_no' => 'BULK-003',
        'family_code' => 'SSP-BULK2',
        'full_name' => 'Orang Lain',
        'class_name' => '1 Alamanda',
        'billing_year' => $billingYear,
        'is_b40' => false,
    ]);

    $response = $this->actingAs($teacher)->post(route('teacher.social-tags.bulk-apply'), [
        'billing_year' => $billingYear,
        'class_name' => 'all',
        'tag_field' => 'is_b40',
        'match_lines' => "1\tMUHAMMAD ZAHIR IMAN\t1 ALAMANDA\n",
    ]);

    $response->assertRedirect();

    expect(Student::query()->where('family_code', 'SSP-BULK1')->where('is_b40', true)->count())->toBe(2);
    expect(Student::query()->where('family_code', 'SSP-BULK2')->where('is_b40', true)->count())->toBe(0);
});

it('shows filtered student list when selecting social tag group', function () {
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
        'student_no' => 'FLT-001',
        'family_code' => 'SSP-FLT1',
        'full_name' => 'Aina',
        'class_name' => '2 Angsana',
        'billing_year' => $billingYear,
        'is_b40' => true,
    ]);

    Student::query()->create([
        'student_no' => 'FLT-002',
        'family_code' => 'SSP-FLT2',
        'full_name' => 'Hafiz',
        'class_name' => '2 Angsana',
        'billing_year' => $billingYear,
        'is_b40' => false,
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.social-tags.index', [
        'billing_year' => $billingYear,
        'tag_filter' => 'is_b40',
    ]));

    $response->assertOk();
    $response->assertSee('Student List');
    $response->assertSee('SSP-FLT1');
    $response->assertDontSee('SSP-FLT2');
});
