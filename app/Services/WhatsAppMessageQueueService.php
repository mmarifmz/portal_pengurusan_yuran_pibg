<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsAppApiThrottleLog;
use App\Models\WhatsAppMessageQueue;
use App\Models\WhatsAppQueueBatch;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WhatsAppMessageQueueService
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $batchMeta
     * @return array{batch_id:string,messages_queued:int,first_scheduled_at:?string,last_scheduled_at:?string}
     */
    public function queueMessages(array $messages, User $queuedBy, array $batchMeta = []): array
    {
        $batchId = (string) ($batchMeta['batch_id'] ?? (string) Str::uuid());
        $messagesQueued = count($messages);
        $firstScheduledAt = collect($messages)
            ->pluck('scheduled_at')
            ->filter()
            ->map(fn ($value) => $value instanceof CarbonInterface ? $value : Carbon::parse((string) $value))
            ->sort()
            ->first();
        $lastScheduledAt = collect($messages)
            ->pluck('scheduled_at')
            ->filter()
            ->map(fn ($value) => $value instanceof CarbonInterface ? $value : Carbon::parse((string) $value))
            ->sort()
            ->last();

        DB::transaction(function () use ($messages, $queuedBy, $batchMeta, $batchId, $messagesQueued, $firstScheduledAt, $lastScheduledAt): void {
            WhatsAppQueueBatch::query()->create([
                'batch_id' => $batchId,
                'message_type' => (string) ($batchMeta['message_type'] ?? WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT),
                'queued_by' => $queuedBy->id,
                'source' => (string) ($batchMeta['source'] ?? 'class_progress'),
                'total_classes_selected' => (int) ($batchMeta['total_classes_selected'] ?? 0),
                'total_classes_queued' => (int) ($batchMeta['total_classes_queued'] ?? 0),
                'total_messages_queued' => $messagesQueued,
                'total_skipped' => (int) ($batchMeta['total_skipped'] ?? 0),
                'meta' => [
                    ...((array) ($batchMeta['meta'] ?? [])),
                    'first_scheduled_at' => $firstScheduledAt?->toIso8601String(),
                    'last_scheduled_at' => $lastScheduledAt?->toIso8601String(),
                ],
            ]);

            foreach ($messages as $message) {
                $scheduledAt = $message['scheduled_at'] ?? now();
                $scheduledAt = $scheduledAt instanceof CarbonInterface ? $scheduledAt : Carbon::parse((string) $scheduledAt);

                WhatsAppMessageQueue::query()->create([
                    'queue_batch_id' => $batchId,
                    'billing_year' => (int) $message['billing_year'],
                    'class_name' => (string) $message['class_name'],
                    'teacher_user_id' => isset($message['teacher_user_id']) && $message['teacher_user_id'] !== ''
                        ? (int) $message['teacher_user_id']
                        : null,
                    'recipient_name' => (string) $message['recipient_name'],
                    'recipient_phone' => (string) $message['recipient_phone'],
                    'message_type' => (string) $message['message_type'],
                    'message_part' => (string) $message['message_part'],
                    'message_segment' => (int) ($message['message_segment'] ?? 1),
                    'segment_count' => (int) ($message['segment_count'] ?? 1),
                    'total_parts' => (int) $message['total_parts'],
                    'part_order' => (int) ($message['part_order'] ?? 1),
                    'message_body' => (string) $message['message_body'],
                    'status' => WhatsAppMessageQueue::STATUS_PENDING,
                    'queued_by' => $queuedBy->id,
                    'queued_at' => now(),
                    'scheduled_at' => $scheduledAt,
                ]);
            }
        });

        return [
            'batch_id' => $batchId,
            'messages_queued' => $messagesQueued,
            'first_scheduled_at' => $firstScheduledAt?->toIso8601String(),
            'last_scheduled_at' => $lastScheduledAt?->toIso8601String(),
        ];
    }

    public function hasRecentDuplicate(string $className, int $billingYear, ?int $teacherUserId = null, int $withinMinutes = 30): bool
    {
        return WhatsAppMessageQueue::query()
            ->where('message_type', WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT)
            ->where('billing_year', $billingYear)
            ->where('class_name', $className)
            ->when($teacherUserId !== null, fn ($query) => $query->where('teacher_user_id', $teacherUserId))
            ->whereIn('status', [WhatsAppMessageQueue::STATUS_PENDING, WhatsAppMessageQueue::STATUS_SENDING])
            ->where('created_at', '>=', now()->subMinutes($withinMinutes))
            ->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $previews
     * @return array{
     *   eligible_classes:int,
     *   total_messages:int,
     *   pending_waiting_count:int,
     *   first_send_at:?string,
     *   last_send_at:?string,
     *   per_class:array<string, array<string, mixed>>,
     *   planned_messages:array<int, array<string, mixed>>,
     *   warning:?string
     * }
     */
    public function estimateScheduleForPreviews(array $previews): array
    {
        $intervalSeconds = $this->sendIntervalSeconds();
        $classGapSeconds = $this->classGapSeconds();
        $dashboard = $this->dashboardSnapshot();
        $anchor = $this->nextAvailableAnchor();
        $plannedMessages = [];
        $perClass = [];
        $partOrder = 1;
        $currentStart = $anchor->copy();

        foreach ($previews as $preview) {
            if (! (bool) ($preview['queue_eligibility']['ready'] ?? false)) {
                continue;
            }

            $generatedMessages = collect($preview['generated_messages'] ?? [])->values();
            if ($generatedMessages->isEmpty()) {
                continue;
            }

            $className = (string) ($preview['class_name'] ?? '');
            $classFirstAt = $currentStart->copy();
            $classLastAt = $currentStart->copy()->addSeconds(max(0, ($generatedMessages->count() - 1) * $intervalSeconds));

            $perClass[$className] = [
                'class_name' => $className,
                'message_count' => $generatedMessages->count(),
                'first_scheduled_at' => $classFirstAt->toIso8601String(),
                'last_scheduled_at' => $classLastAt->toIso8601String(),
            ];

            foreach ($generatedMessages as $index => $message) {
                $scheduledAt = $classFirstAt->copy()->addSeconds($index * $intervalSeconds);

                $plannedMessages[] = [
                    'class_name' => $className,
                    'message_part' => (string) ($message['message_part'] ?? ''),
                    'scheduled_at' => $scheduledAt,
                    'part_order' => $partOrder,
                ];

                $partOrder++;
            }

            $currentStart = $classLastAt->copy()->addSeconds($classGapSeconds);
        }

        $firstSendAt = collect($plannedMessages)->pluck('scheduled_at')->first();
        $lastSendAt = collect($plannedMessages)->pluck('scheduled_at')->last();

        return [
            'eligible_classes' => count($perClass),
            'total_messages' => count($plannedMessages),
            'pending_waiting_count' => (int) ($dashboard['waiting_count'] ?? 0),
            'first_send_at' => $firstSendAt?->toIso8601String(),
            'last_send_at' => $lastSendAt?->toIso8601String(),
            'per_class' => $perClass,
            'planned_messages' => array_map(static fn (array $message): array => [
                ...$message,
                'scheduled_at' => $message['scheduled_at']->toIso8601String(),
            ], $plannedMessages),
            'warning' => $dashboard['pending_warning'] ?? null,
        ];
    }

    /**
     * @return array{
     *   pending:int,
     *   scheduled:int,
     *   sending:int,
     *   sent_today:int,
     *   failed_today:int,
     *   waiting_count:int,
     *   jobs_pending:int,
     *   pending_warning:?string,
     *   processor_warning:?string,
     *   shared_session_note:?string,
     *   last_processed_at:?string,
     *   next_scheduled_at:?string
     * }
     */
    public function dashboardSnapshot(): array
    {
        $pending = WhatsAppMessageQueue::query()
            ->where('status', WhatsAppMessageQueue::STATUS_PENDING)
            ->count();

        $scheduled = WhatsAppMessageQueue::query()
            ->where('status', WhatsAppMessageQueue::STATUS_PENDING)
            ->where('scheduled_at', '>', now())
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

        $jobsPending = $this->pendingQueueJobsCount();
        $waitingCount = $pending + $sending + $jobsPending;

        $lastProcessedAt = collect([
            WhatsAppMessageQueue::query()->whereNotNull('sent_at')->orderByDesc('sent_at')->value('sent_at'),
            WhatsAppMessageQueue::query()->whereNotNull('failed_at')->orderByDesc('failed_at')->value('failed_at'),
        ])
            ->filter()
            ->sortDesc()
            ->first();

        $nextScheduledAt = WhatsAppMessageQueue::query()
            ->where('status', WhatsAppMessageQueue::STATUS_PENDING)
            ->orderByRaw('COALESCE(scheduled_at, queued_at) asc')
            ->value(DB::raw('COALESCE(scheduled_at, queued_at)'));

        $pendingWarning = $waitingCount >= $this->maxPendingBeforeWarning()
            ? "There are currently {$waitingCount} WhatsApp messages waiting. New messages will be scheduled after the existing queue."
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
            'scheduled' => $scheduled,
            'sending' => $sending,
            'sent_today' => $sentToday,
            'failed_today' => $failedToday,
            'waiting_count' => $waitingCount,
            'jobs_pending' => $jobsPending,
            'pending_warning' => $pendingWarning,
            'processor_warning' => $processorWarning,
            'shared_session_note' => config('whatsapp.shared_session')
                ? 'This API key is shared with another app. Messages are scheduled slowly to reduce WhatsApp risk.'
                : null,
            'last_processed_at' => $lastProcessedAt ? (string) $lastProcessedAt : null,
            'next_scheduled_at' => $nextScheduledAt ? (string) $nextScheduledAt : null,
        ];
    }

    /**
     * @return Collection<int, WhatsAppMessageQueue>
     */
    public function recentMessages(int $limit = 50, string $statusFilter = 'all'): Collection
    {
        $query = WhatsAppMessageQueue::query()
            ->with(['teacher:id,name,email', 'queuedBy:id,name,email'])
            ->when($statusFilter === 'scheduled', function ($builder): void {
                $builder->where('status', WhatsAppMessageQueue::STATUS_PENDING)
                    ->where('scheduled_at', '>', now());
            })
            ->when($statusFilter === 'pending', function ($builder): void {
                $builder->where('status', WhatsAppMessageQueue::STATUS_PENDING)
                    ->where(function ($query): void {
                        $query->whereNull('scheduled_at')
                            ->orWhere('scheduled_at', '<=', now());
                    });
            })
            ->when(in_array($statusFilter, [
                WhatsAppMessageQueue::STATUS_SENDING,
                WhatsAppMessageQueue::STATUS_SENT,
                WhatsAppMessageQueue::STATUS_FAILED,
            ], true), fn ($builder) => $builder->where('status', $statusFilter));

        return $query
            ->orderByRaw("
                case
                    when status = 'pending' and scheduled_at > ? then 0
                    when status = 'pending' then 1
                    when status = 'sending' then 2
                    when status = 'failed' then 3
                    else 4
                end asc
            ", [now()])
            ->orderByRaw('COALESCE(scheduled_at, queued_at) asc')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function sendIntervalSeconds(): int
    {
        return max(1, (int) config('whatsapp.send_interval_seconds', 20));
    }

    public function classGapSeconds(): int
    {
        return max(0, (int) config('whatsapp.class_gap_seconds', 30));
    }

    public function minimumSendGapSeconds(): int
    {
        $configured = $this->sendIntervalSeconds();

        if ((bool) config('whatsapp.account_protection_mode', true)) {
            return max(5, $configured);
        }

        return max(1, $configured);
    }

    public function maxPendingBeforeWarning(): int
    {
        return max(1, (int) config('whatsapp.max_pending_before_warning', 10));
    }

    public function nextAvailableAnchor(): CarbonInterface
    {
        $gap = $this->minimumSendGapSeconds();
        $anchor = now()->addSeconds($gap);

        $queueTail = WhatsAppMessageQueue::query()
            ->whereIn('status', [WhatsAppMessageQueue::STATUS_PENDING, WhatsAppMessageQueue::STATUS_SENDING])
            ->selectRaw('MAX(COALESCE(scheduled_at, queued_at, created_at)) as queue_tail')
            ->value('queue_tail');

        if ($queueTail !== null) {
            $anchor = $anchor->max(Carbon::parse((string) $queueTail)->addSeconds($gap));
        }

        if ((bool) config('whatsapp.shared_session', true)) {
            $sharedSentAt = $this->lastSharedSessionSentAt();
            if ($sharedSentAt !== null) {
                $anchor = $anchor->max($sharedSentAt->copy()->addSeconds($gap));
            }
        }

        return $anchor;
    }

    public function lastSharedSessionSentAt(): ?Carbon
    {
        if (! Schema::hasTable('whatsapp_api_throttle_logs')) {
            return null;
        }

        $value = WhatsAppApiThrottleLog::query()
            ->latest('sent_at')
            ->value('sent_at');

        return $value ? Carbon::parse((string) $value) : null;
    }

    private function pendingQueueJobsCount(): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        return (int) DB::table('jobs')
            ->where('queue', 'whatsapp')
            ->count();
    }
}
