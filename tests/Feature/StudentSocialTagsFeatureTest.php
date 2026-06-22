<?php

use App\Models\FamilyBilling;
use App\Models\SocialTag;
use App\Models\Student;
use App\Models\User;

it('filters student directory by student-level social tag without requiring family tag', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;
    $ketuaKelas = SocialTag::query()->create([
        'name' => 'Ketua Kelas',
        'slug' => 'ketua-kelas',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $taggedStudent = Student::query()->create([
        'student_no' => 'FILTER-STUDENT-001',
        'family_code' => 'SSP-FILTER-STUDENT-1',
        'full_name' => 'Aina Ketua',
        'class_name' => '4 Aman',
        'billing_year' => $billingYear,
    ]);
    $taggedStudent->socialTags()->sync([$ketuaKelas->id]);

    Student::query()->create([
        'student_no' => 'FILTER-STUDENT-002',
        'family_code' => 'SSP-FILTER-STUDENT-2',
        'full_name' => 'Hakim Biasa',
        'class_name' => '4 Aman',
        'billing_year' => $billingYear,
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.records', [
        'student_social_tag' => 'ketua-kelas',
    ]));

    $response->assertOk();
    $response->assertSee('Tag Murid / Jawatan Murid');
    $response->assertSee('AINA KETUA');
    $response->assertSee('Ketua Kelas');
    $response->assertDontSee('HAKIM BIASA');
});

it('can combine family-level and student-level tag filters', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;
    $asnaf = SocialTag::query()->create([
        'name' => 'Asnaf',
        'slug' => 'asnaf-combined',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $pengawas = SocialTag::query()->create([
        'name' => 'Pengawas',
        'slug' => 'pengawas-combined',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $matchingBilling = FamilyBilling::query()->create([
        'family_code' => 'SSP-COMBINED-1',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);
    $matchingBilling->socialTags()->sync([$asnaf->id]);

    $matchingStudent = Student::query()->create([
        'student_no' => 'COMBINED-001',
        'family_code' => 'SSP-COMBINED-1',
        'full_name' => 'Aina Match',
        'class_name' => '5 Aman',
        'billing_year' => $billingYear,
    ]);
    $matchingStudent->socialTags()->sync([$pengawas->id]);

    $familyOnlyBilling = FamilyBilling::query()->create([
        'family_code' => 'SSP-COMBINED-2',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);
    $familyOnlyBilling->socialTags()->sync([$asnaf->id]);

    Student::query()->create([
        'student_no' => 'COMBINED-002',
        'family_code' => 'SSP-COMBINED-2',
        'full_name' => 'Hakim Family Only',
        'class_name' => '5 Aman',
        'billing_year' => $billingYear,
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.records', [
        'social_tag' => 'asnaf-combined',
        'student_social_tag' => 'pengawas-combined',
    ]));

    $response->assertOk();
    $response->assertSee('AINA MATCH');
    $response->assertDontSee('HAKIM FAMILY ONLY');
});

it('exports finance accounting with family and student social tag fields', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;
    $familyTag = SocialTag::query()->create([
        'name' => 'B40 Export',
        'slug' => 'b40-export',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $studentTag = SocialTag::query()->create([
        'name' => 'Pancaragam',
        'slug' => 'pancaragam-export',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-EXPORT-TAGS',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);
    $billing->socialTags()->sync([$familyTag->id]);

    $student = Student::query()->create([
        'student_no' => 'EXPORT-TAGS-001',
        'family_code' => 'SSP-EXPORT-TAGS',
        'full_name' => 'Nur Pancaragam',
        'class_name' => '2 Aman',
        'billing_year' => $billingYear,
    ]);
    $student->socialTags()->sync([$studentTag->id]);

    $response = $this->actingAs($admin)->get(route('teacher.finance-accounting.export', [
        'year_a' => $billingYear - 1,
        'year_b' => $billingYear,
    ]));

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('family_social_tags');
    expect($content)->toContain('student_social_tags');
    expect($content)->toContain('B40 Export');
    expect($content)->toContain('Pancaragam');
});
