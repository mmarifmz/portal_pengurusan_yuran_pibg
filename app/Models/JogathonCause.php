<?php

namespace App\Models;

use Database\Factories\JogathonCauseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JogathonCause extends Model
{
    /** @use HasFactory<JogathonCauseFactory> */
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'description',
        'target_amount_sen',
        'sort_order',
        'is_active',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(JogathonCampaign::class, 'campaign_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(JogathonContribution::class, 'cause_id');
    }
}
