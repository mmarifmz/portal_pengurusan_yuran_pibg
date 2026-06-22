<?php

use App\Models\FamilyBilling;
use App\Models\SocialTag;
use App\Models\Student;
use App\Models\User;

it('shows latest family social tags on family profile page', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-FAMTAG1',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $tagA = SocialTag::query()->create([
        'name' => 'Asnaf',
        'slug' => 'asnaf',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $tagB = SocialTag::query()->create([
        'name' => 'Special Approval',
        'slug' => 'special-approval',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $billing->socialTags()->sync([$tagA->id, $tagB->id]);
    $billing->update(['social_tag' => 'Asnaf']);

    Student::query()->create([
        'student_no' => 'FAMTAG-001',
        'family_code' => 'SSP-FAMTAG1',
        'full_name' => 'Sanjanaa Elumalai',
        'class_name' => '4 Azalea',
        'billing_year' => $billingYear,
    ]);

    $response = $this->actingAs($teacher)
        ->get(route('teacher.records.family', ['familyCode' => 'SSP-FAMTAG1']));

    $response->assertOk();
    $response->assertSee('Family Social Tags');
    $response->assertSee('Asnaf');
    $response->assertSee('Special Approval');
});

it('allows system admin to update latest family social tags from family profile page', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-FAMTAG2',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $b40 = SocialTag::query()->firstOrCreate([
        'slug' => 'b40',
    ], [
        'name' => 'B40',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $asnaf = SocialTag::query()->firstOrCreate([
        'slug' => 'asnaf',
    ], [
        'name' => 'Asnaf',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Student::query()->create([
        'student_no' => 'FAMTAG-002',
        'family_code' => 'SSP-FAMTAG2',
        'full_name' => 'Aina',
        'class_name' => '2 Aman',
        'billing_year' => $billingYear,
        'is_b40' => false,
    ]);

    $response = $this->actingAs($admin)->patch(route('teacher.records.family.social-tags.update', [
        'familyCode' => 'SSP-FAMTAG2',
    ]), [
        'social_tag_ids' => [$b40->id, $asnaf->id],
    ]);

    $response->assertRedirect(route('teacher.records.family', ['familyCode' => 'SSP-FAMTAG2']));

    expect($billing->fresh()->social_tag)->toBe('B40');
    expect($billing->fresh()->socialTags()->pluck('name')->all())->toBe(['B40', 'Asnaf']);
    expect(Student::query()->where('family_code', 'SSP-FAMTAG2')->value('is_b40'))->toBeTrue();
});

it('forbids teacher from updating family social tags', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    FamilyBilling::query()->create([
        'family_code' => 'SSP-FAMTAG3',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $tag = SocialTag::query()->firstOrCreate([
        'slug' => 'b40-lockdown',
    ], [
        'name' => 'B40 Lockdown',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    Student::query()->create([
        'student_no' => 'FAMTAG-003',
        'family_code' => 'SSP-FAMTAG3',
        'full_name' => 'Teacher View Only',
        'class_name' => '2 Aman',
        'billing_year' => $billingYear,
        'is_b40' => false,
    ]);

    $response = $this->actingAs($teacher)->patch(route('teacher.records.family.social-tags.update', [
        'familyCode' => 'SSP-FAMTAG3',
    ]), [
        'social_tag_ids' => [$tag->id],
    ]);

    $response->assertForbidden();
});

it('shows family tags separately from student jawatan tags on family profile page', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;
    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-STUDENTTAG1',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $asnaf = SocialTag::query()->create([
        'name' => 'Asnaf',
        'slug' => 'asnaf-student-profile',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $ketuaKelas = SocialTag::query()->create([
        'name' => 'Ketua Kelas',
        'slug' => 'ketua-kelas-profile',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $student = Student::query()->create([
        'student_no' => 'STUDENTTAG-001',
        'family_code' => 'SSP-STUDENTTAG1',
        'full_name' => 'Nur Ketua',
        'class_name' => '4 Aman',
        'billing_year' => $billingYear,
    ]);

    $billing->socialTags()->sync([$asnaf->id]);
    $student->socialTags()->sync([$ketuaKelas->id]);

    $response = $this->actingAs($teacher)
        ->get(route('teacher.records.family', ['familyCode' => 'SSP-STUDENTTAG1']));

    $response->assertOk();
    $response->assertSee('Tag Keluarga');
    $response->assertSee('Asnaf');
    $response->assertSee('Tag Murid / Jawatan Murid');
    $response->assertSee('Ketua Kelas');
});

it('allows system admin to assign and remove student social tags', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;
    $student = Student::query()->create([
        'student_no' => 'STUDENTTAG-002',
        'family_code' => 'SSP-STUDENTTAG2',
        'full_name' => 'Aina Pengawas',
        'class_name' => '5 Aman',
        'billing_year' => $billingYear,
    ]);

    $tag = SocialTag::query()->create([
        'name' => 'Pengawas',
        'slug' => 'pengawas',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($admin)->patch(route('teacher.records.students.tags.update', $student), [
        'target_type' => 'student',
        'family_code' => 'SSP-STUDENTTAG2',
        'social_tag_ids' => [$tag->id],
    ])->assertRedirect(route('teacher.records.family', ['familyCode' => 'SSP-STUDENTTAG2']).'#student-tags-'.$student->id);

    expect($student->fresh()->socialTags()->pluck('name')->all())->toBe(['Pengawas']);

    $this->actingAs($admin)->patch(route('teacher.records.students.tags.update', $student), [
        'target_type' => 'student',
        'family_code' => 'SSP-STUDENTTAG2',
    ])->assertRedirect();

    expect($student->fresh()->socialTags()->count())->toBe(0);
});

it('rejects student tag assignment when student is outside selected family', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'STUDENTTAG-003',
        'family_code' => 'SSP-CORRECT',
        'full_name' => 'Wrong Family',
        'class_name' => '3 Aman',
        'billing_year' => (int) now()->year,
    ]);
    $tag = SocialTag::query()->create([
        'name' => 'PRS',
        'slug' => 'prs',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($admin)->patch(route('teacher.records.students.tags.update', $student), [
        'target_type' => 'student',
        'family_code' => 'SSP-WRONG',
        'social_tag_ids' => [$tag->id],
    ])->assertSessionHasErrors('family_code');

    expect($student->fresh()->socialTags()->count())->toBe(0);
});

it('prevents duplicate student tag assignment in one request', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'STUDENTTAG-004',
        'family_code' => 'SSP-DUPE',
        'full_name' => 'Duplicate Tag',
        'class_name' => '6 Aman',
        'billing_year' => (int) now()->year,
    ]);
    $tag = SocialTag::query()->create([
        'name' => 'Librarian',
        'slug' => 'librarian',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($admin)->patch(route('teacher.records.students.tags.update', $student), [
        'target_type' => 'student',
        'family_code' => 'SSP-DUPE',
        'social_tag_ids' => [$tag->id, $tag->id],
    ])->assertSessionHasErrors('social_tag_ids.1');

    expect($student->fresh()->socialTags()->count())->toBe(0);
});

it('rejects inactive tags when assigning student social tags', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $student = Student::query()->create([
        'student_no' => 'STUDENTTAG-005',
        'family_code' => 'SSP-INACTIVE-TAG',
        'full_name' => 'Inactive Tag',
        'class_name' => '6 Aman',
        'billing_year' => (int) now()->year,
    ]);
    $tag = SocialTag::query()->create([
        'name' => 'Inactive Jawatan',
        'slug' => 'inactive-jawatan',
        'is_active' => false,
        'sort_order' => 0,
    ]);

    $this->actingAs($admin)->patch(route('teacher.records.students.tags.update', $student), [
        'target_type' => 'student',
        'family_code' => 'SSP-INACTIVE-TAG',
        'social_tag_ids' => [$tag->id],
    ])->assertSessionHasErrors('social_tag_ids.0');

    expect($student->fresh()->socialTags()->count())->toBe(0);
});
