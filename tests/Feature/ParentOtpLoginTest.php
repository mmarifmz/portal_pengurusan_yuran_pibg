<?php

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