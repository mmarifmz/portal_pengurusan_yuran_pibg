<?php

use App\Models\User;
use App\Models\WhatsAppMessageQueue;
use App\Services\WhatsAppTacSender;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('super admin can generate whatsapp web onboarding invite link without queueing or calling wasender', function () {
    config()->set('teacher.default_password', 'change-this-password');

    $sender = \Mockery::mock(WhatsAppTacSender::class);
    $sender->shouldNotReceive('sendMessage');
    $this->app->instance(WhatsAppTacSender::class, $sender);

    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $teacher = User::factory()->create([
        'role' => 'teacher',
        'name' => 'Cikgu Nadia',
        'email' => 'nadia@example.test',
        'phone' => '+60139906160',
        'class_name' => '4 ANGSANA',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->postJson(route('super-teacher.teachers.onboarding-invites.generate'), [
        'teacher_ids' => [$teacher->id],
        'temporary_password' => 'change-this-password',
        'dashboard_url' => route('teacher.dashboard', absolute: true),
        'reset_passwords' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('generated_count', 1)
        ->assertJsonPath('invites.0.teacher_id', $teacher->id);

    $invite = $response->json('invites.0');

    expect((string) $invite['wa_link'])->toStartWith('https://wa.me/60139906160?text=');
    expect(rawurldecode((string) parse_url((string) $invite['wa_link'], PHP_URL_QUERY)))->toContain('Assalamualaikum / Salam Sejahtera');
    expect(WhatsAppMessageQueue::query()->count())->toBe(0);

    $teacher->refresh();
    expect($teacher->onboarding_invite_status)->toBe('generated');
    expect($teacher->onboarding_invite_generated_at)->not->toBeNull();
});

it('super teacher cannot access onboarding invite controls', function () {
    $manager = User::factory()->create([
        'role' => 'super_teacher',
        'email_verified_at' => now(),
    ]);

    $teacher = User::factory()->create([
        'role' => 'teacher',
        'phone' => '+60139906160',
        'class_name' => '4 ANGSANA',
        'is_active' => true,
    ]);

    $page = $this->actingAs($manager)->get(route('super-teacher.teachers.index'));
    $page->assertOk();
    $page->assertDontSee('Teacher Onboarding Invite');
    $page->assertDontSee('Invite via WhatsApp');

    $this->actingAs($manager)->post(route('super-teacher.teachers.onboarding-invites.generate'), [
        'teacher_ids' => [$teacher->id],
        'temporary_password' => 'change-this-password',
        'dashboard_url' => route('teacher.dashboard', absolute: true),
    ])->assertForbidden();
});

it('mark sent updates manual onboarding invite tracking', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $teacher = User::factory()->create([
        'role' => 'teacher',
        'phone' => '+60139906160',
        'class_name' => '4 ANGSANA',
        'is_active' => true,
        'onboarding_invite_status' => 'generated',
        'onboarding_invite_generated_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($admin)->post(route('super-teacher.teachers.mark-invite-sent', $teacher), [
        'confirm_mark_sent' => '1',
    ]);

    $response->assertRedirect(route('super-teacher.teachers.index'));

    $teacher->refresh();
    expect($teacher->onboarding_invite_status)->toBe('sent_manual');
    expect($teacher->onboarding_invite_method)->toBe('whatsapp_web');
    expect($teacher->onboarding_invite_sent_by)->toBe($admin->id);
    expect($teacher->onboarding_invite_sent_manually_at)->not->toBeNull();
});
