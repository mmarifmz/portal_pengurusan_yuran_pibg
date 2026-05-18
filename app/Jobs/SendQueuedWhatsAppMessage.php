<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageQueue;
use App\Services\WaSenderService;
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
            $delivery = $waSenderService->sendText((string) $message->recipient_phone, (string) $message->message_body);

            $message->forceFill([
                'status' => WhatsAppMessageQueue::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
                'provider_message_id' => $delivery['message_id'] ?? null,
                'provider_response' => $delivery['response'] ?? null,
            ])->save();
        } catch (\Throwable $exception) {
            $message->forceFill([
                'status' => WhatsAppMessageQueue::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}
