<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'queue_batch_id',
    'billing_year',
    'class_name',
    'teacher_user_id',
    'recipient_name',
    'recipient_phone',
    'message_type',
    'message_part',
    'message_segment',
    'segment_count',
    'total_parts',
    'message_body',
    'status',
    'queued_by',
    'queued_at',
    'scheduled_at',
    'sending_at',
    'sent_at',
    'failed_at',
    'error_message',
    'provider_message_id',
    'provider_response',
    'part_order',
])]
class WhatsAppMessageQueue extends Model
{
    protected $table = 'whatsapp_message_queues';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const MESSAGE_TYPE_CLASS_PAYMENT_REPORT = 'class_payment_report';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_year' => 'integer',
            'message_segment' => 'integer',
            'segment_count' => 'integer',
            'total_parts' => 'integer',
            'part_order' => 'integer',
            'queued_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'sending_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'provider_response' => 'array',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function queuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'queued_by');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WhatsAppQueueBatch::class, 'queue_batch_id', 'batch_id');
    }
}
