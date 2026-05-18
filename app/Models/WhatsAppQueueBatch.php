<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'batch_id',
    'message_type',
    'queued_by',
    'source',
    'total_classes_selected',
    'total_classes_queued',
    'total_messages_queued',
    'total_skipped',
    'meta',
])]
class WhatsAppQueueBatch extends Model
{
    protected $table = 'whatsapp_queue_batches';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function queuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'queued_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessageQueue::class, 'queue_batch_id', 'batch_id');
    }
}
