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

it('updates latest family social tags from family profile page', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
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

    $response = $this->actingAs($teacher)->patch(route('teacher.records.family.social-tags.update', [
        'familyCode' => 'SSP-FAMTAG2',
    ]), [
        'social_tag_ids' => [$b40->id, $asnaf->id],
    ]);

    $response->assertRedirect(route('teacher.records.family', ['familyCode' => 'SSP-FAMTAG2']));

    expect($billing->fresh()->social_tag)->toBe('B40');
    expect($billing->fresh()->socialTags()->pluck('name')->all())->toBe(['B40', 'Asnaf']);
    expect(Student::query()->where('family_code', 'SSP-FAMTAG2')->value('is_b40'))->toBeTrue();
});
