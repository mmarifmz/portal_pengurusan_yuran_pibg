<?php

use App\Models\User;
use App\Models\WhatsAppMessageQueue;

it('shows whatsapp queue status in artisan output', function () {
    $queuedBy = User::factory()->create(['role' => 'system_admin']);

    WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '5 Bestari',
        'recipient_name' => 'Guru Bestari',
        'recipient_phone' => '+60112223333',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Pending message',
        'status' => WhatsAppMessageQueue::STATUS_PENDING,
        'queued_by' => $queuedBy->id,
        'queued_at' => now(),
        'total_parts' => 1,
    ]);

    $this->artisan('whatsapp:queue-status --show=5')
        ->expectsOutputToContain('WhatsApp queue status')
        ->expectsOutputToContain('Pending ready: 1')
        ->assertExitCode(0);
});

it('retries failed rate-limited whatsapp rows via artisan command', function () {
    config()->set('services.wasender.min_interval_seconds', 0);

    $queuedBy = User::factory()->create(['role' => 'system_admin']);

    $message = WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '5 Bestari',
        'recipient_name' => 'Guru Bestari',
        'recipient_phone' => '+60112223333',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Failed message',
        'status' => WhatsAppMessageQueue::STATUS_FAILED,
        'queued_by' => $queuedBy->id,
        'queued_at' => now()->subMinute(),
        'failed_at' => now()->subSeconds(10),
        'error_message' => 'Rate limited by Wasender. Will retry after 4 seconds.',
        'total_parts' => 1,
    ]);

    $this->artisan('whatsapp:retry-failed --rate-limited-only --limit=5')
        ->expectsOutputToContain('Reset 1 failed WhatsApp queue rows back to pending.')
        ->assertExitCode(0);

    $message->refresh();

    expect($message->status)->toBe(WhatsAppMessageQueue::STATUS_PENDING);
    expect($message->failed_at)->toBeNull();
    expect($message->sending_at)->toBeNull();
    expect($message->queued_at)->not->toBeNull();
});

it('retries stuck sending whatsapp rows via artisan command', function () {
    config()->set('services.wasender.min_interval_seconds', 0);

    $queuedBy = User::factory()->create(['role' => 'system_admin']);

    $message = WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '6 Cekal',
        'recipient_name' => 'Guru Cekal',
        'recipient_phone' => '+60114445555',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Stuck sending message',
        'status' => WhatsAppMessageQueue::STATUS_SENDING,
        'queued_by' => $queuedBy->id,
        'queued_at' => now()->subMinutes(30),
        'sending_at' => now()->subMinutes(20),
        'total_parts' => 1,
    ]);

    $this->artisan('whatsapp:retry-stuck --minutes=15 --limit=5')
        ->expectsOutputToContain('Reset 1 stuck WhatsApp queue rows back to pending.')
        ->assertExitCode(0);

    $message->refresh();

    expect($message->status)->toBe(WhatsAppMessageQueue::STATUS_PENDING);
    expect($message->sending_at)->toBeNull();
    expect((string) $message->error_message)->toContain('Recovered from stuck sending state');
});
