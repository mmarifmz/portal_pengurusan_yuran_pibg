<?php

use App\Models\FamilyBilling;
use App\Models\FamilyBillingPhone;
use App\Models\ParentLoginAudit;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use App\Models\User;

it('sends tac for valid parent phone and stores hashed otp', function () {
    User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
        'email' => 'parent.otp@example.test',
    ]);

    $response = $this->post(route('parent.login.request'), [
        'phone' => '0123456789',
    ]);

    $response->assertRedirect(route('parent.login.verify.form'));
    $response->assertSessionHas('parent_otp_phone', '0123456789');
    $response->assertSessionHas('parent_otp_debug_code');

    $this->assertDatabaseHas('parent_login_otps', [
        'phone' => '0123456789',
        'used_at' => null,
    ]);
});

it('redirects unknown phone to child search flow instead of showing login error', function () {
    $response = $this->post(route('parent.login.request'), [
        'phone' => '0146364001',
    ]);

    $response->assertRedirect(route('parent.search', ['contact' => '0146364001']));
    $response->assertSessionHas('status');
    $this->assertDatabaseCount('parent_login_otps', 0);
});

it('logs parent in using valid tac', function () {
    User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
        'email' => 'parent.otp@example.test',
    ]);

    $requestResponse = $this->post(route('parent.login.request'), [
        'phone' => '0123456789',
    ]);

    $requestResponse->assertRedirect(route('parent.login.verify.form'));

    $pin = session('parent_otp_debug_code');

    $verifyResponse = $this->post(route('parent.login.verify.submit'), [
        'pin' => $pin,
    ]);

    $verifyResponse->assertRedirect(route('parent.search')); 
    $verifyResponse->assertSessionHas('parent_child_selection_completed', false);

    $this->assertAuthenticated();
    expect(auth()->user()->role)->toBe('parent');
});

it('blocks repeated tac request within cooldown while previous tac is still active', function () {
    User::factory()->create([
        'role' => 'parent',
        'phone' => '0191112222',
        'email' => 'parent.cooldown.block@example.test',
    ]);

    $first = $this->post(route('parent.login.request'), [
        'phone' => '0191112222',
    ]);
    $first->assertRedirect(route('parent.login.verify.form'));

    $second = $this->from(route('parent.login.form'))->post(route('parent.login.request'), [
        'phone' => '0191112222',
    ]);

    $second->assertRedirect(route('parent.login.form'));
    $second->assertSessionHasErrors(['phone']);
    $this->assertDatabaseCount('parent_login_otps', 1);
    $this->assertDatabaseHas('parent_login_otps', [
        'phone' => '0191112222',
        'used_at' => null,
    ]);
});

it('allows tac resend after cooldown and invalidates previous active tac', function () {
    User::factory()->create([
        'role' => 'parent',
        'phone' => '0193334444',
        'email' => 'parent.cooldown.allow@example.test',
    ]);

    $first = $this->post(route('parent.login.request'), [
        'phone' => '0193334444',
    ]);
    $first->assertRedirect(route('parent.login.verify.form'));

    ParentLoginOtp::query()
        ->where('phone', '0193334444')
        ->whereNull('used_at')
        ->update(['created_at' => now()->subSeconds(120)]);

    $second = $this->post(route('parent.login.request'), [
        'phone' => '0193334444',
    ]);

    $second->assertStatus(302);
    $second->assertSessionHasNoErrors();
    $second->assertSessionHas('parent_otp_phone', '0193334444');
    $this->assertDatabaseCount('parent_login_otps', 2);
    expect(ParentLoginOtp::query()
        ->where('phone', '0193334444')
        ->whereNull('used_at')
        ->count())->toBe(1);
});

it('skips tac and logs parent in directly after first successful activation', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0133434086',
        'email' => 'parent.activated@example.test',
        'is_active' => true,
    ]);

    ParentLoginAudit::query()->create([
        'user_id' => $parent->id,
        'phone' => '0133434086',
        'normalized_phone' => '60133434086',
        'logged_in_at' => now(),
    ]);

    $response = $this->post(route('parent.login.request'), [
        'phone' => '0133434086',
    ]);

    $response->assertRedirect(route('parent.search'));
    $response->assertSessionHas('status');
    $this->assertAuthenticatedAs($parent);
    $this->assertDatabaseCount('parent_login_otps', 0);
});

it('blocks login when parent account is disabled even before sending tac', function () {
    User::factory()->create([
        'role' => 'parent',
        'phone' => '0133000000',
        'email' => 'parent.disabled@example.test',
        'is_active' => false,
    ]);

    $response = $this->from(route('parent.login.form'))->post(route('parent.login.request'), [
        'phone' => '0133000000',
    ]);

    $response->assertRedirect(route('parent.login.form'));
    $response->assertSessionHasErrors(['phone']);
    $this->assertGuest();
});

it('stores intended checkout when tac is requested from bayar yuran flow', function () {
    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F712',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP1001',
        'family_code' => 'SSP-F712',
        'full_name' => 'Qisya Izz Hanan',
        'class_name' => '1 Azalea',
        'parent_name' => 'Nurul Syahiriza',
        'parent_phone' => '0123456789',
    ]);

    $response = $this->post(route('parent.login.request'), [
        'phone' => '0123456789',
        'family_billing_id' => $billing->id,
    ]);

    $response->assertRedirect(route('parent.login.verify.form'));
    $response->assertSessionHas('parent_login_intended_checkout', $billing->id);
    $this->assertDatabaseHas('users', [
        'role' => 'parent',
        'phone' => '0123456789',
    ]);
    $this->assertDatabaseHas('students', [
        'family_code' => 'SSP-F712',
        'parent_phone' => '0123456789',
    ]);
});

it('redirects parent to the selected checkout after valid tac verification', function () {
    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F712',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP1001',
        'family_code' => 'SSP-F712',
        'full_name' => 'Qisya Izz Hanan',
        'class_name' => '1 Azalea',
        'parent_name' => 'Nurul Syahiriza',
        'parent_phone' => '0123456789',
    ]);

    $requestResponse = $this->post(route('parent.login.request'), [
        'phone' => '0123456789',
        'family_billing_id' => $billing->id,
    ]);

    $requestResponse->assertRedirect(route('parent.login.verify.form'));

    $pin = session('parent_otp_debug_code');

    $verifyResponse = $this->post(route('parent.login.verify.submit'), [
        'pin' => $pin,
    ]);

    $verifyResponse->assertRedirect(route('parent.payments.checkout', $billing));
    $this->assertAuthenticated();
});


it('allows TAC request when phone is registered in family phone list', function () {
    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F900',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP1900',
        'family_code' => 'SSP-F900',
        'full_name' => 'Aina Nur',
        'class_name' => '3 Amanah',
        'parent_name' => 'Ibu Aina',
        'parent_phone' => '0111111111',
    ]);

    FamilyBillingPhone::query()->create([
        'family_billing_id' => $billing->id,
        'phone' => '0132000000',
        'normalized_phone' => '60132000000',
    ]);

    $response = $this->post(route('parent.login.request'), [
        'phone' => '0132000000',
        'family_billing_id' => $billing->id,
    ]);

    $response->assertRedirect(route('parent.login.verify.form'));
    $this->assertDatabaseHas('users', [
        'role' => 'parent',
        'phone' => '0132000000',
    ]);
});

it('allows adding fifth phone for a paid family via reset flow', function () {
    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F901',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP1901',
        'family_code' => 'SSP-F901',
        'full_name' => 'Aqil Firdaus',
        'class_name' => '4 Bestari',
        'parent_name' => 'Bapa Aqil',
        'parent_phone' => '0120000000',
    ]);

    foreach (['0120000001', '0120000002', '0120000003', '0120000004'] as $seedPhone) {
        FamilyBillingPhone::query()->create([
            'family_billing_id' => $billing->id,
            'phone' => $seedPhone,
            'normalized_phone' => '6'.$seedPhone,
        ]);
    }

    $response = $this->post(route('parent.login.request'), [
        'phone' => '0139998888',
        'family_billing_id' => $billing->id,
        'confirm_phone_reset' => 1,
    ]);

    $response->assertRedirect(route('parent.login.verify.form'));

    $this->assertDatabaseHas('family_billing_phones', [
        'family_billing_id' => $billing->id,
        'normalized_phone' => '60139998888',
    ]);
});

it('rejects adding sixth phone for a paid family', function () {
    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F902',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP1902',
        'family_code' => 'SSP-F902',
        'full_name' => 'Irfan Hakim',
        'class_name' => '5 Cemerlang',
        'parent_name' => 'Ibu Irfan',
        'parent_phone' => '0121000000',
    ]);

    foreach (['0121000001', '0121000002', '0121000003', '0121000004', '0121000005'] as $seedPhone) {
        FamilyBillingPhone::query()->create([
            'family_billing_id' => $billing->id,
            'phone' => $seedPhone,
            'normalized_phone' => '6'.$seedPhone,
        ]);
    }

    $response = $this->from(route('parent.login.form', ['billing' => $billing->id]))->post(route('parent.login.request'), [
        'phone' => '0130007777',
        'family_billing_id' => $billing->id,
        'confirm_phone_reset' => 1,
    ]);

    $response->assertSessionHasErrors(['phone']);

    $this->assertDatabaseMissing('family_billing_phones', [
        'family_billing_id' => $billing->id,
        'normalized_phone' => '60130007777',
    ]);
});

it('routes selected family to checkout when family already has a registered parent phone', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0137778888',
        'email' => 'parent.select.family@example.test',
        'email_verified_at' => now(),
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F950',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP1950',
        'family_code' => 'SSP-F950',
        'full_name' => 'Nadia Hana',
        'class_name' => '3 Aktif',
        'parent_name' => 'Ibu Nadia',
        'parent_phone' => '0115431234',
    ]);

    FamilyBillingPhone::query()->create([
        'family_billing_id' => $billing->id,
        'phone' => '0115431234',
        'normalized_phone' => '60115431234',
    ]);

    $response = $this->actingAs($parent)->post(route('parent.search.select', $billing));

    $response->assertRedirect(route('parent.payments.checkout', $billing));
    $response->assertSessionHas('parent_child_selection_completed', true);
    $response->assertSessionHas('status');

    $this->assertDatabaseHas('family_billing_phones', [
        'family_billing_id' => $billing->id,
        'normalized_phone' => '60137778888',
    ]);
});

it('routes selected family to dashboard when this is the first registered family phone', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0171112222',
        'email' => 'parent.first.family@example.test',
        'email_verified_at' => now(),
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F951',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP1951',
        'family_code' => 'SSP-F951',
        'full_name' => 'Aiman Danish',
        'class_name' => '2 Dinamik',
        'parent_name' => 'Bapa Aiman',
        'parent_phone' => null,
    ]);

    $response = $this->actingAs($parent)->post(route('parent.search.select', $billing));

    $response->assertRedirect(route('parent.dashboard'));
    $response->assertSessionHas('parent_child_selection_completed', true);

    $this->assertDatabaseHas('family_billing_phones', [
        'family_billing_id' => $billing->id,
        'normalized_phone' => '60171112222',
    ]);
});
it('blocks manual checkout URL access when child selection session is missing', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0183334444',
        'email' => 'parent.manual.block@example.test',
        'email_verified_at' => now(),
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F960',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    FamilyBillingPhone::query()->create([
        'family_billing_id' => $billing->id,
        'phone' => '0183334444',
        'normalized_phone' => '60183334444',
    ]);

    $response = $this->actingAs($parent)->get(route('parent.payments.checkout', $billing));

    $response->assertForbidden();
});

it('allows checkout URL access after child selection session is completed for that family', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0187779999',
        'email' => 'parent.manual.allow@example.test',
        'email_verified_at' => now(),
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F961',
        'billing_year' => 2026,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    FamilyBillingPhone::query()->create([
        'family_billing_id' => $billing->id,
        'phone' => '0187779999',
        'normalized_phone' => '60187779999',
    ]);

    $response = $this->actingAs($parent)
        ->withSession([
            'parent_child_selection_completed' => true,
            'parent_selected_family_billing_id' => $billing->id,
        ])
        ->get(route('parent.payments.checkout', $billing));

    $response->assertOk();
});
