<?php

use App\Models\User;
use App\Models\FamilyBilling;
use App\Models\ParentLoginInvite;

it('allows system admin to view payment tester management page', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    User::factory()->create([
        'role' => 'parent',
        'name' => 'Parent One',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('system.payment-testers.index'));

    $response->assertOk();
    $response->assertSee('Payment Tester Users');
    $response->assertSee('PARENT ONE');
});

it('allows system admin to toggle parent payment tester mode', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'is_payment_tester' => false,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)->patch(route('system.payment-testers.update', $parent), [
        'is_payment_tester' => 1,
    ]);

    $response->assertRedirect(route('system.payment-testers.index', ['q' => '']));

    $parent->refresh();
    expect($parent->is_payment_tester)->toBeTrue();
    expect($parent->isParentTester())->toBeTrue();
});

it('blocks non system admin from payment tester management page', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($teacher)->get(route('system.payment-testers.index'));

    $response->assertForbidden();
});

it('allows system admin to generate portal test invite without whatsapp send', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)->post(route('system.payment-testers.portal-test-invite'), [
        'phone' => '01140030076',
        'send_whatsapp' => '0',
    ]);

    $response->assertRedirect(route('system.payment-testers.index', ['q' => '']));

    $dummyFamily = FamilyBilling::query()
        ->where('family_code', 'TEST-DUMMY-PORTAL')
        ->where('billing_year', 2099)
        ->first();

    expect($dummyFamily)->not->toBeNull();
    expect(ParentLoginInvite::query()->count())->toBe(1);

    $invite = ParentLoginInvite::query()->latest('id')->first();
    expect($invite->family_billing_id)->toBe($dummyFamily->id);
    expect($invite->expires_at)->not->toBeNull();
});

it('test invite link can log parent in and open checkout', function () {
    $familyBilling = FamilyBilling::query()->create([
        'family_code' => 'TEST-DUMMY-PORTAL',
        'billing_year' => 2099,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '01140030076',
        'is_payment_tester' => true,
        'email_verified_at' => now(),
    ]);

    $invite = ParentLoginInvite::query()->create([
        'family_billing_id' => $familyBilling->id,
        'user_id' => $parent->id,
        'phone' => '01140030076',
        'normalized_phone' => '601140030076',
        'token' => str_repeat('a', 80),
        'expires_at' => now()->addHours(24),
        'sent_at' => now(),
    ]);

    $response = $this->get(route('parent.invite.login', ['token' => $invite->token]));

    $response->assertRedirect(route('parent.payments.checkout', $familyBilling));
    $this->assertAuthenticatedAs($parent);
    expect($invite->fresh()->used_at)->not->toBeNull();
});
