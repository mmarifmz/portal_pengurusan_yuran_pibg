<?php

use App\Jobs\SendQueuedWhatsAppMessage;
use App\Models\User;
use App\Models\WhatsAppMessageQueue;
use App\Services\WaSenderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('wasender service sends text using configured api', function () {
    config()->set('services.wasender.api_key', 'test-api-key');
    config()->set('services.wasender.api_url', 'https://wa.example.test/api');

    Http::fake([
        'https://wa.example.test/api/send-message' => Http::response([
            'data' => [
                'status' => 'queued',
                'msgId' => 'MSG-001',
                'jid' => 'jid-001',
            ],
        ], 200),
    ]);

    $response = app(WaSenderService::class)->sendText('0139906160', 'Hello teacher');

    expect($response['status'])->toBe('queued');
    expect($response['message_id'])->toBe('MSG-001');
});

it('queued whatsapp job marks message as sent', function () {
    config()->set('services.wasender.api_key', 'test-api-key');
    config()->set('services.wasender.api_url', 'https://wa.example.test/api');
    config()->set('whatsapp.send_interval_seconds', 1);
    config()->set('whatsapp.account_protection_mode', false);
    config()->set('whatsapp.shared_session', false);

    Http::fake([
        'https://wa.example.test/api/send-message' => Http::response([
            'data' => [
                'status' => 'queued',
                'msgId' => 'MSG-002',
                'jid' => 'jid-002',
            ],
        ], 200),
    ]);

    $queuedBy = User::factory()->create(['role' => 'system_admin']);
    $teacher = User::factory()->create(['role' => 'teacher']);

    $message = WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '1 Angsana',
        'teacher_user_id' => $teacher->id,
        'recipient_name' => 'Teacher Angsana',
        'recipient_phone' => '+60139906160',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Test message',
        'status' => WhatsAppMessageQueue::STATUS_PENDING,
        'queued_by' => $queuedBy->id,
        'queued_at' => now(),
        'scheduled_at' => now(),
        'total_parts' => 1,
        'part_order' => 1,
    ]);

    app()->call([new SendQueuedWhatsAppMessage($message->id), 'handle']);

    expect($message->fresh()->status)->toBe(WhatsAppMessageQueue::STATUS_SENT);
    expect($message->fresh()->sent_at)->not->toBeNull();
});

it('queued whatsapp job marks message as failed when wasender errors', function () {
    config()->set('services.wasender.api_key', 'test-api-key');
    config()->set('services.wasender.api_url', 'https://wa.example.test/api');
    config()->set('whatsapp.send_interval_seconds', 1);
    config()->set('whatsapp.account_protection_mode', false);
    config()->set('whatsapp.shared_session', false);

    Http::fake([
        'https://wa.example.test/api/send-message' => Http::response([
            'error' => 'Invalid request',
        ], 500),
    ]);

    $queuedBy = User::factory()->create(['role' => 'system_admin']);
    $teacher = User::factory()->create(['role' => 'teacher']);

    $message = WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '1 Angsana',
        'teacher_user_id' => $teacher->id,
        'recipient_name' => 'Teacher Angsana',
        'recipient_phone' => '+60139906160',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Test message',
        'status' => WhatsAppMessageQueue::STATUS_PENDING,
        'queued_by' => $queuedBy->id,
        'queued_at' => now(),
        'scheduled_at' => now(),
        'total_parts' => 1,
        'part_order' => 1,
    ]);

    try {
        app()->call([new SendQueuedWhatsAppMessage($message->id), 'handle']);
    } catch (\Throwable) {
        // Expected so the queue job can be retried by Laravel when used asynchronously.
    }

    expect($message->fresh()->status)->toBe(WhatsAppMessageQueue::STATUS_FAILED);
    expect($message->fresh()->failed_at)->not->toBeNull();
});

it('queued whatsapp job defers rate-limited messages back to pending', function () {
    config()->set('services.wasender.api_key', 'test-api-key');
    config()->set('services.wasender.api_url', 'https://wa.example.test/api');
    config()->set('whatsapp.send_interval_seconds', 1);
    config()->set('whatsapp.account_protection_mode', false);
    config()->set('whatsapp.shared_session', false);

    Http::fake([
        'https://wa.example.test/api/send-message' => Http::response([
            'message' => 'You have account protection enabled. You can only send 1 message every 5 seconds.',
            'retry_after' => 4,
            'help' => 'Disable Account Protection from your session settings page.',
        ], 429),
    ]);

    $queuedBy = User::factory()->create(['role' => 'system_admin']);
    $teacher = User::factory()->create(['role' => 'teacher']);

    $message = WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '1 Angsana',
        'teacher_user_id' => $teacher->id,
        'recipient_name' => 'Teacher Angsana',
        'recipient_phone' => '+60139906160',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Test message',
        'status' => WhatsAppMessageQueue::STATUS_PENDING,
        'queued_by' => $queuedBy->id,
        'queued_at' => now(),
        'scheduled_at' => now(),
        'total_parts' => 1,
        'part_order' => 1,
    ]);

    app()->call([new SendQueuedWhatsAppMessage($message->id), 'handle']);

    $message->refresh();

    expect($message->status)->toBe(WhatsAppMessageQueue::STATUS_PENDING);
    expect($message->failed_at)->toBeNull();
    expect($message->sending_at)->toBeNull();
    expect($message->scheduled_at?->isFuture())->toBeTrue();
    expect((string) $message->error_message)->toContain('Rate limited by Wasender');
});

it('queued whatsapp job yields when global send lock is already held', function () {
    config()->set('services.wasender.api_key', 'test-api-key');
    config()->set('services.wasender.api_url', 'https://wa.example.test/api');
    config()->set('whatsapp.send_interval_seconds', 20);
    config()->set('whatsapp.account_protection_mode', true);
    config()->set('whatsapp.shared_session', false);

    Http::fake();

    $queuedBy = User::factory()->create(['role' => 'system_admin']);
    $teacher = User::factory()->create(['role' => 'teacher']);

    $message = WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '1 Angsana',
        'teacher_user_id' => $teacher->id,
        'recipient_name' => 'Teacher Angsana',
        'recipient_phone' => '+60139906160',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Test message',
        'status' => WhatsAppMessageQueue::STATUS_PENDING,
        'queued_by' => $queuedBy->id,
        'queued_at' => now(),
        'scheduled_at' => now(),
        'total_parts' => 1,
        'part_order' => 1,
    ]);

    $lock = Cache::lock('whatsapp-api-send-lock', 60);
    expect($lock->get())->toBeTrue();

    try {
        app()->call([new SendQueuedWhatsAppMessage($message->id), 'handle']);
    } finally {
        $lock->release();
    }

    $message->refresh();

    expect($message->status)->toBe(WhatsAppMessageQueue::STATUS_PENDING);
    expect($message->scheduled_at?->isFuture())->toBeTrue();
    expect((string) $message->error_message)->toContain('shared send lock');
});
