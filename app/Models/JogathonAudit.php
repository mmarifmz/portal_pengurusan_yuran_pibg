<?php

namespace App\Models;

use Database\Factories\JogathonAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JogathonAudit extends Model
{
    /** @use HasFactory<JogathonAuditFactory> */
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'auditable_type',
        'auditable_id',
        'action',
        'before_values',
        'after_values',
        'reason',
        'actor_user_id',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(JogathonCampaign::class, 'campaign_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
