<?php

use App\Models\User;

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
    $response->assertSee('Parent One');
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