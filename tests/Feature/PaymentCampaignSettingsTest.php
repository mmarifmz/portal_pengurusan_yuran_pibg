<?php

use App\Models\FamilyBilling;
use App\Models\PaymentCampaignSetting;
use App\Models\SocialTag;
use App\Models\Student;
use App\Models\User;

function createCampaignParentFamily(string $familyCode, ?string $socialTag = null): array
{
    $digits = preg_replace('/\D+/', '', $familyCode) ?? '';
    $phone = '0129'.str_pad(substr($digits !== '' ? $digits : (string) abs(crc32($familyCode)), -6), 6, '0', STR_PAD_LEFT);

    $billing = FamilyBilling::query()->create([
        'family_code' => $familyCode,
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
        'social_tag' => $socialTag,
    ]);

    Student::query()->create([
        'student_no' => 'CMP-001-'.$familyCode,
        'family_code' => $familyCode,
        'full_name' => 'Kempen Parent',
        'class_name' => '5 Cekal',
        'parent_name' => 'Ibu Kempen',
        'parent_phone' => $phone,
        'parent_email' => strtolower($familyCode).'@example.test',
        'billing_year' => now()->year,
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => $phone,
        'email' => strtolower($familyCode).'@example.test',
        'name' => 'Ibu Kempen',
        'email_verified_at' => now(),
    ]);

    return [$billing, $parent];
}

function campaignBillingSession(FamilyBilling $billing): array
{
    return [
        'parent_child_selection_completed' => true,
        'parent_selected_family_billing_id' => $billing->id,
    ];
}

it('allows only system admin to access payment campaign settings page', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('system.payment-campaign-settings.index'))
        ->assertOk()
        ->assertSee('Tetapan Kempen Bayaran');

    $this->actingAs($teacher)
        ->get(route('system.payment-campaign-settings.index'))
        ->assertForbidden();
});

it('prevents saving a campaign with all payment options disabled', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)->post(route('system.payment-campaign-settings.save'), [
        'campaign_name' => 'Kempen Kosong',
        'split_2_visibility' => 'all',
        'split_3_visibility' => 'all',
    ]);

    $response->assertSessionHasErrors('campaign_name');
    expect(PaymentCampaignSetting::query()->count())->toBe(0);
});

it('keeps only one active campaign at a time', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $existing = PaymentCampaignSetting::query()->create([
        'campaign_name' => 'Kempen Lama',
        'is_active' => true,
        'allow_single_payment' => true,
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post(route('system.payment-campaign-settings.save'), [
        'campaign_name' => 'Kempen Baharu',
        'is_active' => '1',
        'allow_single_payment' => '1',
        'split_2_visibility' => 'all',
        'split_3_visibility' => 'all',
    ]);

    $response->assertRedirect(route('system.payment-campaign-settings.index'));

    expect($existing->fresh()->is_active)->toBeFalse();
    expect(PaymentCampaignSetting::query()->where('is_active', true)->count())->toBe(1);
    expect(PaymentCampaignSetting::query()->where('is_active', true)->value('campaign_name'))->toBe('Kempen Baharu');
});

it('loads the selected campaign into the form for editing', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $socialTag = SocialTag::query()->create([
        'name' => 'Asnaf',
        'slug' => 'asnaf',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $setting = PaymentCampaignSetting::query()->create([
        'campaign_name' => 'Kempen Edit Asnaf',
        'is_active' => false,
        'allow_single_payment' => false,
        'allow_split_payment' => true,
        'allow_split_2' => true,
        'split_2_visibility' => 'social_tag',
        'split_2_social_tag' => $socialTag->name,
        'split_2_social_tag_id' => $socialTag->id,
        'allow_split_3' => false,
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('system.payment-campaign-settings.index', [
        'campaign_id' => $setting->id,
        'mode' => 'edit',
    ]));

    $response->assertOk();
    $response->assertSee('Edit Kempen');
    $response->assertSee('Kempen Edit Asnaf');
    $response->assertSee('name="setting_id" value="'.$setting->id.'"', false);
    $response->assertSee('Anda sedang mengedit kempen', false);
});

it('loads a duplicated campaign into the form as a new draft', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $socialTag = SocialTag::query()->create([
        'name' => 'Special Approval',
        'slug' => 'special-approval',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $setting = PaymentCampaignSetting::query()->create([
        'campaign_name' => 'Kempen Salin',
        'is_active' => true,
        'allow_single_payment' => false,
        'allow_split_payment' => true,
        'allow_split_3' => true,
        'split_3_visibility' => 'social_tag',
        'split_3_social_tag' => $socialTag->name,
        'split_3_social_tag_id' => $socialTag->id,
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('system.payment-campaign-settings.index', [
        'campaign_id' => $setting->id,
        'mode' => 'duplicate',
    ]));

    $response->assertOk();
    $response->assertSee('Duplicate Kempen');
    $response->assertSee('Kempen Salin (Salinan)');
    $response->assertSee('name="setting_id" value=""', false);
    $response->assertSee('Anda sedang menyalin kempen', false);
});

it('creates a new campaign when saving a duplicated draft', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $original = PaymentCampaignSetting::query()->create([
        'campaign_name' => 'Kempen Asal',
        'is_active' => false,
        'allow_single_payment' => true,
        'allow_split_payment' => false,
        'allow_split_2' => false,
        'allow_split_3' => false,
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post(route('system.payment-campaign-settings.save'), [
        'campaign_name' => 'Kempen Asal (Salinan)',
        'allow_single_payment' => '1',
        'allow_split_payment' => '1',
        'allow_split_2' => '1',
        'split_2_visibility' => 'all',
        'split_3_visibility' => 'all',
    ]);

    $response->assertRedirect(route('system.payment-campaign-settings.index'));

    expect(PaymentCampaignSetting::query()->count())->toBe(2);
    expect($original->fresh()->campaign_name)->toBe('Kempen Asal');
    expect(PaymentCampaignSetting::query()->where('campaign_name', 'Kempen Asal (Salinan)')->exists())->toBeTrue();
});

it('keeps split 2 enabled when it is selected even if the parent split toggle is not posted', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $socialTag = SocialTag::query()->firstOrCreate([
        'name' => 'B40',
    ], [
        'slug' => 'b40-regression',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($admin)->post(route('system.payment-campaign-settings.save'), [
        'campaign_name' => 'Kempen Ansuran 2',
        'is_active' => '1',
        'allow_single_payment' => '1',
        'allow_split_2' => '1',
        'split_2_visibility' => 'social_tag',
        'split_2_social_tag_id' => (string) $socialTag->id,
        'split_3_visibility' => 'all',
    ]);

    $response->assertRedirect(route('system.payment-campaign-settings.index'));

    $setting = PaymentCampaignSetting::query()->where('campaign_name', 'Kempen Ansuran 2')->firstOrFail();

    expect($setting->allow_split_payment)->toBeTrue();
    expect($setting->allow_split_2)->toBeTrue();
    expect($setting->split_2_social_tag_id)->toBe($socialTag->id);
});

it('defaults to single payment only when no active campaign exists', function () {
    [$billing, $parent] = createCampaignParentFamily('SSP-CAMPAIGN-DEFAULT', null);

    $response = $this->actingAs($parent)
        ->withSession(campaignBillingSession($billing))
        ->get(route('parent.payments.review', $billing));

    $response->assertOk();
    $response->assertSee('Bayar Penuh');
    $response->assertDontSee('Bayar 2 Kali');
    $response->assertDontSee('Bayar 3 Kali');
});

it('shows split 2 option only to matching social tag families when campaign requires it', function () {
    PaymentCampaignSetting::query()->create([
        'campaign_name' => 'Kempen B40',
        'is_active' => true,
        'allow_single_payment' => false,
        'allow_split_payment' => true,
        'allow_split_2' => true,
        'split_2_visibility' => 'social_tag',
        'split_2_social_tag' => 'B40',
        'allow_split_3' => false,
    ]);

    [$eligibleBilling, $eligibleParent] = createCampaignParentFamily('SSP-CAMPAIGN-B40', 'B40');
    [$otherBilling, $otherParent] = createCampaignParentFamily('SSP-CAMPAIGN-OTHER', 'ASNAF');

    $eligibleResponse = $this->actingAs($eligibleParent)
        ->withSession(campaignBillingSession($eligibleBilling))
        ->get(route('parent.payments.review', $eligibleBilling));

    $eligibleResponse->assertOk();
    $eligibleResponse->assertSee('Ansuran 2 Kali');
    $eligibleResponse->assertDontSee('Bayar Penuh');

    auth()->logout();

    $otherResponse = $this->actingAs($otherParent)
        ->withSession(campaignBillingSession($otherBilling))
        ->get(route('parent.payments.review', $otherBilling));

    $otherResponse->assertOk();
    $otherResponse->assertSee('Tiada pilihan bayaran tersedia');
    $otherResponse->assertDontSee('Ansuran 2 Kali');
});

it('uses real social tag assignments for split-payment campaign eligibility', function () {
    $socialTag = SocialTag::query()->firstOrCreate([
        'name' => 'Special Approval',
    ], [
        'slug' => 'special-approval',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    PaymentCampaignSetting::query()->create([
        'campaign_name' => 'Kempen Special Approval',
        'is_active' => true,
        'allow_single_payment' => false,
        'allow_split_payment' => true,
        'allow_split_2' => true,
        'split_2_visibility' => 'social_tag',
        'split_2_social_tag' => 'Special Approval',
        'split_2_social_tag_id' => $socialTag->id,
        'allow_split_3' => false,
    ]);

    [$eligibleBilling, $eligibleParent] = createCampaignParentFamily('SSP-CAMPAIGN-REALTAG', null);
    [$otherBilling, $otherParent] = createCampaignParentFamily('SSP-CAMPAIGN-REALTAG-OTHER', null);

    $eligibleBilling->socialTags()->syncWithoutDetaching([$socialTag->id]);

    $eligibleResponse = $this->actingAs($eligibleParent)
        ->withSession(campaignBillingSession($eligibleBilling))
        ->get(route('parent.payments.review', $eligibleBilling));

    $eligibleResponse->assertOk();
    $eligibleResponse->assertSee('Ansuran 2 Kali');
    $eligibleResponse->assertDontSee('Bayar Penuh');

    auth()->logout();

    $otherResponse = $this->actingAs($otherParent)
        ->withSession(campaignBillingSession($otherBilling))
        ->get(route('parent.payments.review', $otherBilling));

    $otherResponse->assertOk();
    $otherResponse->assertSee('Tiada pilihan bayaran tersedia');
    $otherResponse->assertDontSee('Ansuran 2 Kali');
});
