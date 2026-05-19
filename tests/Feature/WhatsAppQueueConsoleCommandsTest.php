<?php

use App\Models\User;
use App\Models\WhatsAppMessageQueue;
use Illuminate\Support\Facades\Http;

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
        'scheduled_at' => now(),
        'total_parts' => 1,
        'part_order' => 1,
    ]);

    $this->artisan('whatsapp:queue-status --show=5')
        ->expectsOutputToContain('WhatsApp queue status')
        ->expectsOutputToContain('Pending ready: 1')
        ->assertExitCode(0);
});

it('retries failed rate-limited whatsapp rows via artisan command', function () {
    config()->set('whatsapp.send_interval_seconds', 1);

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
        'scheduled_at' => now()->subMinute(),
        'failed_at' => now()->subSeconds(10),
        'error_message' => 'Rate limited by Wasender. Will retry after 4 seconds.',
        'total_parts' => 1,
        'part_order' => 1,
    ]);

    $this->artisan('whatsapp:retry-failed --rate-limited-only --limit=5')
        ->expectsOutputToContain('Reset 1 failed WhatsApp queue rows back to pending.')
        ->assertExitCode(0);

    $message->refresh();

    expect($message->status)->toBe(WhatsAppMessageQueue::STATUS_PENDING);
    expect($message->failed_at)->toBeNull();
    expect($message->sending_at)->toBeNull();
    expect($message->scheduled_at)->not->toBeNull();
});

it('retries stuck sending whatsapp rows via artisan command', function () {
    config()->set('whatsapp.send_interval_seconds', 1);

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
        'scheduled_at' => now()->subMinutes(30),
        'sending_at' => now()->subMinutes(20),
        'total_parts' => 1,
        'part_order' => 1,
    ]);

    $this->artisan('whatsapp:retry-stuck --minutes=15 --limit=5')
        ->expectsOutputToContain('Reset 1 stuck WhatsApp queue rows back to pending.')
        ->assertExitCode(0);

    $message->refresh();

    expect($message->status)->toBe(WhatsAppMessageQueue::STATUS_PENDING);
    expect($message->sending_at)->toBeNull();
    expect((string) $message->error_message)->toContain('Recovered from stuck sending state');
});

it('worker only processes messages whose scheduled_at has arrived', function () {
    config()->set('services.wasender.api_key', 'test-api-key');
    config()->set('services.wasender.api_url', 'https://wa.example.test/api');
    config()->set('whatsapp.send_interval_seconds', 1);
    config()->set('whatsapp.account_protection_mode', false);
    config()->set('whatsapp.shared_session', false);

    Http::fake([
        'https://wa.example.test/api/send-message' => Http::response([
            'data' => [
                'status' => 'queued',
                'msgId' => 'MSG-PROCESS-001',
            ],
        ], 200),
    ]);

    $queuedBy = User::factory()->create(['role' => 'system_admin']);

    $readyMessage = WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '1 Angsana',
        'recipient_name' => 'Ready Teacher',
        'recipient_phone' => '+60112223333',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Ready message',
        'status' => WhatsAppMessageQueue::STATUS_PENDING,
        'queued_by' => $queuedBy->id,
        'queued_at' => now(),
        'scheduled_at' => now()->subSecond(),
        'total_parts' => 1,
        'part_order' => 1,
    ]);

    $futureMessage = WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '1 Alamanda',
        'recipient_name' => 'Future Teacher',
        'recipient_phone' => '+60114445555',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Future message',
        'status' => WhatsAppMessageQueue::STATUS_PENDING,
        'queued_by' => $queuedBy->id,
        'queued_at' => now(),
        'scheduled_at' => now()->addMinutes(2),
        'total_parts' => 1,
        'part_order' => 2,
    ]);

    $this->artisan('whatsapp:process-queue --limit=10')
        ->expectsOutputToContain('Processed queue message #'.$readyMessage->id)
        ->assertExitCode(0);

    expect($readyMessage->fresh()->status)->toBe(WhatsAppMessageQueue::STATUS_SENT);
    expect($futureMessage->fresh()->status)->toBe(WhatsAppMessageQueue::STATUS_PENDING);
});
