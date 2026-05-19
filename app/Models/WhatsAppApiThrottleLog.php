<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'app_name',
    'message_id',
    'recipient_phone',
    'sent_at',
    'api_response_status',
])]
class WhatsAppApiThrottleLog extends Model
{
    public $timestamps = false;

    protected $table = 'whatsapp_api_throttle_logs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'message_id' => 'integer',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
