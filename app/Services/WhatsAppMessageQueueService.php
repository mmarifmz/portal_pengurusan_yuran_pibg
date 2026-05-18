<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsAppMessageQueue;
use App\Models\WhatsAppQueueBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppMessageQueueService
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $batchMeta
     * @return array{batch_id:string,messages_queued:int}
     */
    public function queueMessages(array $messages, User $queuedBy, array $batchMeta = []): array
    {
        $batchId = (string) ($batchMeta['batch_id'] ?? (string) Str::uuid());
        $messagesQueued = count($messages);

        DB::transaction(function () use ($messages, $queuedBy, $batchMeta, $batchId, $messagesQueued): void {
            WhatsAppQueueBatch::query()->create([
                'batch_id' => $batchId,
                'message_type' => (string) ($batchMeta['message_type'] ?? WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT),
                'queued_by' => $queuedBy->id,
                'source' => (string) ($batchMeta['source'] ?? 'class_progress'),
                'total_classes_selected' => (int) ($batchMeta['total_classes_selected'] ?? 0),
                'total_classes_queued' => (int) ($batchMeta['total_classes_queued'] ?? 0),
                'total_messages_queued' => $messagesQueued,
                'total_skipped' => (int) ($batchMeta['total_skipped'] ?? 0),
                'meta' => $batchMeta['meta'] ?? null,
            ]);

            foreach ($messages as $message) {
                WhatsAppMessageQueue::query()->create([
                    'queue_batch_id' => $batchId,
                    'billing_year' => (int) $message['billing_year'],
                    'class_name' => (string) $message['class_name'],
                    'teacher_user_id' => (int) $message['teacher_user_id'],
                    'recipient_name' => (string) $message['recipient_name'],
                    'recipient_phone' => (string) $message['recipient_phone'],
                    'message_type' => (string) $message['message_type'],
                    'message_part' => (string) $message['message_part'],
                    'message_segment' => (int) ($message['message_segment'] ?? 1),
                    'segment_count' => (int) ($message['segment_count'] ?? 1),
                    'total_parts' => (int) $message['total_parts'],
                    'message_body' => (string) $message['message_body'],
                    'status' => WhatsAppMessageQueue::STATUS_PENDING,
                    'queued_by' => $queuedBy->id,
                    'queued_at' => now(),
                ]);
            }
        });

        return [
            'batch_id' => $batchId,
            'messages_queued' => $messagesQueued,
        ];
    }

    public function hasRecentDuplicate(string $className, int $billingYear, int $withinMinutes = 15): bool
    {
        return WhatsAppMessageQueue::query()
            ->where('message_type', WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT)
            ->where('billing_year', $billingYear)
            ->where('class_name', $className)
            ->where('queued_at', '>=', now()->subMinutes($withinMinutes))
            ->exists();
    }

    /**
     * @return array{
     *   pending:int,
     *   sending:int,
     *   sent_today:int,
     *   failed_today:int,
     *   pending_warning:?string,
     *   processor_warning:?string,
     *   last_processed_at:?string
     * }
     */
    public function dashboardSnapshot(): array
    {
        $pending = WhatsAppMessageQueue::query()
            ->where('status', WhatsAppMessageQueue::STATUS_PENDING)
            ->count();

        $sending = WhatsAppMessageQueue::query()
            ->where('status', WhatsAppMessageQueue::STATUS_SENDING)
            ->count();

        $sentToday = WhatsAppMessageQueue::query()
            ->where('status', WhatsAppMessageQueue::STATUS_SENT)
            ->whereDate('sent_at', today())
            ->count();

        $failedToday = WhatsAppMessageQueue::query()
            ->where('status', WhatsAppMessageQueue::STATUS_FAILED)
            ->whereDate('failed_at', today())
            ->count();

        $lastProcessedAt = collect([
            WhatsAppMessageQueue::query()->whereNotNull('sent_at')->orderByDesc('sent_at')->value('sent_at'),
            WhatsAppMessageQueue::query()->whereNotNull('failed_at')->orderByDesc('failed_at')->value('failed_at'),
        ])
            ->filter()
            ->sortDesc()
            ->first();

        $pendingWarning = $pending >= 20
            ? "There are currently {$pending} pending WhatsApp messages in queue."
            : null;

        $processorWarning = null;
        if ($pending > 0) {
            $lastTouchedPending = WhatsAppMessageQueue::query()
                ->whereIn('status', [WhatsAppMessageQueue::STATUS_PENDING, WhatsAppMessageQueue::STATUS_SENDING])
                ->orderByDesc('updated_at')
                ->value('updated_at');

            $referenceTime = $lastProcessedAt ?: $lastTouchedPending;
            if ($referenceTime !== null && now()->diffInMinutes($referenceTime) >= 15) {
                $processorWarning = 'The WhatsApp queue processor looks idle. Pending messages may take longer than usual.';
            }
        }

        return [
            'pending' => $pending,
            'sending' => $sending,
            'sent_today' => $sentToday,
            'failed_today' => $failedToday,
            'pending_warning' => $pendingWarning,
            'processor_warning' => $processorWarning,
            'last_processed_at' => $lastProcessedAt ? (string) $lastProcessedAt : null,
        ];
    }

    /**
     * @return Collection<int, WhatsAppMessageQueue>
     */
    public function recentMessages(int $limit = 50): Collection
    {
        return WhatsAppMessageQueue::query()
            ->with(['teacher:id,name,email', 'queuedBy:id,name,email'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
