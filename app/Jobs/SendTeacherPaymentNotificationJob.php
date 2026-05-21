<?php

namespace App\Jobs;

use App\Exceptions\WaSenderRateLimitException;
use App\Models\TeacherPaymentNotification;
use App\Models\WhatsAppApiThrottleLog;
use App\Services\WaSenderService;
use App\Services\WhatsAppMessageQueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SendTeacherPaymentNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $notificationId
    ) {
        $this->onQueue('teacher-notification');
    }

    public function handle(WaSenderService $waSenderService, WhatsAppMessageQueueService $queueService): void
    {
        $notification = TeacherPaymentNotification::query()->find($this->notificationId);

        if (! $notification || in_array($notification->status, [
            TeacherPaymentNotification::STATUS_CANCELLED,
            TeacherPaymentNotification::STATUS_SENT,
        ], true)) {
            return;
        }

        $lock = Cache::lock('whatsapp-api-send-lock', max(1, (int) config('whatsapp.api_send_lock_seconds', 60)));

        if (! $lock->get()) {
            $notification->markRetrying('Another WhatsApp worker is currently using the shared send lock.');
            $this->release($queueService->minimumSendGapSeconds());

            return;
        }

        try {
            $throttleUntil = $queueService->lastSharedSessionSentAt();
            if ($throttleUntil instanceof Carbon) {
                $nextAllowedAt = $throttleUntil->copy()->addSeconds($queueService->minimumSendGapSeconds());

                if ($nextAllowedAt->isFuture()) {
                    $notification->markRetrying('Waiting for shared WaSender session cooldown before the next API call.');
                    $this->release(max(1, now()->diffInSeconds($nextAllowedAt)));

                    return;
                }
            }

            $notification->markProcessing();

            try {
                $delivery = $waSenderService->sendText((string) $notification->teacher_phone, (string) $notification->message_body);

                $notification->markSent($delivery);

                WhatsAppApiThrottleLog::query()->create([
                    'app_name' => (string) config('whatsapp.app_name', config('app.name', 'Portal Yuran PIBG')),
                    'message_id' => $notification->id,
                    'recipient_phone' => (string) $notification->teacher_phone,
                    'sent_at' => now(),
                    'api_response_status' => (string) ($delivery['status'] ?? 'queued'),
                ]);
            } catch (WaSenderRateLimitException $exception) {
                if ($this->attempts() < $this->tries) {
                    $notification->markRetrying(sprintf(
                        'Rate limited by Wasender. Will retry after %d seconds.',
                        max(1, $exception->retryAfterSeconds)
                    ));
                    $this->release(max(1, $exception->retryAfterSeconds));

                    return;
                }

                $notification->markFailed($exception->getMessage());

                throw $exception;
            } catch (Throwable $throwable) {
                if ($this->attempts() < $this->tries) {
                    $notification->markRetrying($throwable->getMessage());

                    throw $throwable;
                }

                $notification->markFailed($throwable->getMessage());

                throw $throwable;
            }
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(Throwable $throwable): void
    {
        $notification = TeacherPaymentNotification::query()->find($this->notificationId);

        if (! $notification || $notification->isSent() || $notification->status === TeacherPaymentNotification::STATUS_CANCELLED) {
            return;
        }

        $notification->markFailed($throwable->getMessage());
    }
}
