<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiAccessLog extends Model
{
    protected $fillable = [
        'teacher_id',
        'api_key_id',
        'endpoint',
        'method',
        'query_text',
        'request_ip',
        'user_agent',
        'response_status',
        'result_count',
        'error_message',
        'execution_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'result_count' => 'integer',
            'execution_time_ms' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(TeacherApiKey::class, 'api_key_id');
    }
}
