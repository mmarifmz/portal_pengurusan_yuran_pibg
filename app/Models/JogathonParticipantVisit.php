<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JogathonParticipantVisit extends Model
{
    protected $fillable = [
        'campaign_id',
        'participant_id',
        'source',
        'channel',
        'access_point',
        'url',
        'referrer',
        'user_agent',
        'ip_hash',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(JogathonCampaign::class, 'campaign_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(JogathonParticipant::class, 'participant_id');
    }
}
