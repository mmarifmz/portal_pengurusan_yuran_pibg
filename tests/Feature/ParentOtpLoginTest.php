<?php

use App\Models\FamilyBilling;
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

    $verifyResponse->assertRedirect(route('parent.dashboard'));

    $this->assertAuthenticated();
    expect(auth()->user()->role)->toBe('parent');
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
