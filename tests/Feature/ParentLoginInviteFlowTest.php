<?php

use App\Models\FamilyBilling;
use App\Models\FamilyBillingPhone;
use App\Models\ParentLoginInvite;
use App\Models\ParentLoginOtp;
use App\Models\User;

it('sends manual invite from stuck tac row and stores 24-hour token', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $family = FamilyBilling::query()->create([
        'family_code' => 'SSP-INVT',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    FamilyBillingPhone::query()->create([
        'family_billing_id' => $family->id,
        'phone' => '0171111222',
        'normalized_phone' => '60171111222',
    ]);

    ParentLoginOtp::query()->create([
        'user_id' => null,
        'phone' => '0171111222',
        'code_hash' => 'hash',
        'channel' => 'whatsapp',
        'expires_at' => now()->subMinutes(30),
        'used_at' => null,
        'attempts' => 1,
    ]);

    $response = $this->actingAs($teacher)->post(route('teacher.family-login-monitor.invite.send'), [
        'family_billing_id' => $family->id,
        'phone' => '0171111222',
    ]);

    $response->assertRedirect(route('teacher.family-login-monitor'));

    $this->assertDatabaseHas('parent_login_invites', [
        'family_billing_id' => $family->id,
        'phone' => '0171111222',
        'normalized_phone' => '60171111222',
    ]);
});

it('logs parent in via invite link and redirects to checkout', function () {
    $family = FamilyBilling::query()->create([
        'family_code' => 'SSP-LINK',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    FamilyBillingPhone::query()->create([
        'family_billing_id' => $family->id,
        'phone' => '0195555666',
        'normalized_phone' => '60195555666',
    ]);

    $invite = ParentLoginInvite::query()->create([
        'family_billing_id' => $family->id,
        'user_id' => null,
        'phone' => '0195555666',
        'normalized_phone' => '60195555666',
        'token' => str_repeat('b', 80),
        'expires_at' => now()->addHours(24),
        'sent_at' => now(),
        'used_at' => null,
    ]);

    $response = $this->get(route('parent.invite.login', ['token' => $invite->token]));

    $response->assertRedirect(route('parent.payments.checkout', $family));

    $parent = User::query()->where('role', 'parent')->where('phone', '0195555666')->first();
    expect($parent)->not->toBeNull();
    $this->assertAuthenticatedAs($parent);

    $invite->refresh();
    expect($invite->used_at)->not->toBeNull();
});
