<?php

namespace App\Jobs;

use App\Exceptions\WaSenderRateLimitException;
use App\Models\WhatsAppApiThrottleLog;
use App\Models\WhatsAppMessageQueue;
use App\Services\WaSenderService;
use App\Services\WhatsAppMessageQueueService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SendQueuedWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 180, 300];

    public function __construct(private readonly int $queueMessageId)
    {
        $this->onQueue('whatsapp');
    }

    public function handle(WaSenderService $waSenderService, WhatsAppMessageQueueService $queueService): void
    {
        $message = WhatsAppMessageQueue::query()->find($this->queueMessageId);

        if (! $message || $message->status === WhatsAppMessageQueue::STATUS_SENT) {
            return;
        }

        if (! in_array($message->status, [WhatsAppMessageQueue::STATUS_PENDING, WhatsAppMessageQueue::STATUS_SENDING], true)) {
            return;
        }

        if ($message->scheduled_at !== null && $message->scheduled_at->isFuture()) {
            return;
        }

        $lock = Cache::lock('whatsapp-api-send-lock', max(1, (int) config('whatsapp.api_send_lock_seconds', 60)));

        if (! $lock->get()) {
            $this->reschedulePending($message, now()->addSeconds($queueService->minimumSendGapSeconds()), 'Another WhatsApp worker is currently using the shared send lock.');

            return;
        }

        try {
            $throttleUntil = $this->sharedThrottleUntil($queueService);
            if ($throttleUntil !== null && $throttleUntil->isFuture()) {
                $this->reschedulePending($message, $throttleUntil, 'Waiting for shared WaSender session cooldown before the next API call.');

                return;
            }

            $message->forceFill([
                'status' => WhatsAppMessageQueue::STATUS_SENDING,
                'sending_at' => now(),
                'error_message' => null,
            ])->save();

            try {
                $delivery = $waSenderService->sendText((string) $message->recipient_phone, (string) $message->message_body);

                $message->forceFill([
                    'status' => WhatsAppMessageQueue::STATUS_SENT,
                    'sent_at' => now(),
                    'failed_at' => null,
                    'error_message' => null,
                    'provider_message_id' => $delivery['message_id'] ?? null,
                    'provider_response' => [
                        'payload' => $delivery['response'] ?? null,
                        'headers' => $delivery['headers'] ?? [],
                    ],
                ])->save();

                WhatsAppApiThrottleLog::query()->create([
                    'app_name' => (string) config('whatsapp.app_name', config('app.name', 'Portal Yuran PIBG')),
                    'message_id' => $message->id,
                    'recipient_phone' => (string) $message->recipient_phone,
                    'sent_at' => now(),
                    'api_response_status' => (string) ($delivery['status'] ?? 'queued'),
                ]);
            } catch (WaSenderRateLimitException $exception) {
                $retryAt = now()->addSeconds(max(1, $exception->retryAfterSeconds));

                $message->forceFill([
                    'status' => WhatsAppMessageQueue::STATUS_PENDING,
                    'scheduled_at' => $retryAt,
                    'sending_at' => null,
                    'failed_at' => null,
                    'error_message' => sprintf(
                        'Rate limited by Wasender. Will retry after %d seconds.',
                        max(1, $exception->retryAfterSeconds)
                    ),
                    'provider_response' => [
                        'rate_limited' => true,
                        'retry_after' => $exception->retryAfterSeconds,
                        'payload' => $exception->providerResponse,
                        'headers' => $exception->responseHeaders,
                    ],
                ])->save();

                return;
            } catch (\Throwable $exception) {
                $message->forceFill([
                    'status' => WhatsAppMessageQueue::STATUS_FAILED,
                    'failed_at' => now(),
                    'error_message' => $exception->getMessage(),
                ])->save();

                throw $exception;
            }
        } finally {
            optional($lock)->release();
        }
    }

    private function sharedThrottleUntil(WhatsAppMessageQueueService $queueService): ?CarbonInterface
    {
        $lastSentAt = $queueService->lastSharedSessionSentAt();

        if ($lastSentAt === null) {
            return null;
        }

        return $lastSentAt->copy()->addSeconds($queueService->minimumSendGapSeconds());
    }

    private function reschedulePending(WhatsAppMessageQueue $message, CarbonInterface $scheduledAt, string $reason): void
    {
        $message->forceFill([
            'status' => WhatsAppMessageQueue::STATUS_PENDING,
            'scheduled_at' => $scheduledAt,
            'sending_at' => null,
            'failed_at' => null,
            'error_message' => $reason,
        ])->save();
    }
}
