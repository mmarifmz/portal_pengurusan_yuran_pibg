<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageQueue;
use App\Services\WaSenderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

    public function handle(WaSenderService $waSenderService): void
    {
        $message = WhatsAppMessageQueue::query()->find($this->queueMessageId);

        if (! $message || $message->status === WhatsAppMessageQueue::STATUS_SENT) {
            return;
        }

        if (! in_array($message->status, [WhatsAppMessageQueue::STATUS_PENDING, WhatsAppMessageQueue::STATUS_SENDING], true)) {
            return;
        }

        $message->forceFill([
            'status' => WhatsAppMessageQueue::STATUS_SENDING,
            'sending_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $delivery = $this->sendWithThrottle(
                fn () => $waSenderService->sendText((string) $message->recipient_phone, (string) $message->message_body)
            );

            $message->forceFill([
                'status' => WhatsAppMessageQueue::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
                'provider_message_id' => $delivery['message_id'] ?? null,
                'provider_response' => $delivery['response'] ?? null,
            ])->save();
        } catch (\Throwable $exception) {
            $retryAfterSeconds = $this->extractRetryAfterSeconds($exception);

            if ($retryAfterSeconds !== null) {
                $this->setNextAvailableAt($retryAfterSeconds);

                $message->forceFill([
                    'status' => WhatsAppMessageQueue::STATUS_PENDING,
                    'queued_at' => now()->addSeconds($retryAfterSeconds),
                    'sending_at' => null,
                    'failed_at' => null,
                    'error_message' => sprintf(
                        'Rate limited by Wasender. Will retry after %d seconds. %s',
                        $retryAfterSeconds,
                        $exception->getMessage()
                    ),
                    'provider_response' => [
                        'rate_limited' => true,
                        'retry_after' => $retryAfterSeconds,
                        'error' => $exception->getMessage(),
                    ],
                ])->save();

                return;
            }

            $message->forceFill([
                'status' => WhatsAppMessageQueue::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function sendWithThrottle(callable $callback): array
    {
        $cooldownSeconds = max(0, (float) config('services.wasender.min_interval_seconds', 5.5));

        if ($cooldownSeconds <= 0) {
            return $callback();
        }

        return Cache::lock('wasender-send-lock', (int) ceil($cooldownSeconds) + 10)
            ->block(10, function () use ($callback, $cooldownSeconds): array {
                $nextAvailableAt = (float) Cache::get('wasender-next-available-at', 0);
                $remainingSeconds = $nextAvailableAt - microtime(true);

                if ($remainingSeconds > 0) {
                    usleep((int) ceil($remainingSeconds * 1_000_000));
                }

                $delivery = $callback();

                Cache::put(
                    'wasender-next-available-at',
                    microtime(true) + $cooldownSeconds,
                    now()->addMinutes(10)
                );

                return $delivery;
            });
    }

    private function setNextAvailableAt(int $retryAfterSeconds): void
    {
        Cache::put(
            'wasender-next-available-at',
            microtime(true) + max(1, $retryAfterSeconds),
            now()->addMinutes(10)
        );
    }

    private function extractRetryAfterSeconds(\Throwable $exception): ?int
    {
        $message = (string) $exception->getMessage();
        $normalized = mb_strtolower($message);

        if (! str_contains($normalized, 'account protection') && ! str_contains($normalized, 'retry_after')) {
            return null;
        }

        if (preg_match('/"retry_after"\s*:\s*(\d+)/i', $message, $matches) === 1) {
            return max(1, (int) ($matches[1] ?? 0));
        }

        $jsonStart = strpos($message, '{');
        if ($jsonStart !== false) {
            $payload = json_decode(substr($message, $jsonStart), true);
            $retryAfter = is_array($payload) ? ($payload['retry_after'] ?? null) : null;

            if (is_numeric($retryAfter)) {
                return max(1, (int) ceil((float) $retryAfter));
            }
        }

        return 5;
    }
}
