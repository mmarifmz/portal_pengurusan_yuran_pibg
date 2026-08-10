<?php

namespace App\Models;

use App\Support\JogathonAmount;
use Database\Factories\JogathonContributionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JogathonContribution extends Model
{
    /** @use HasFactory<JogathonContributionFactory> */
    use HasFactory;

    public const SOURCE_ONLINE = 'online';

    public const SOURCE_PHYSICAL_CARD = 'physical_card';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESSFUL = 'successful';

    public const STATUS_FINALISED = 'finalised';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'campaign_id',
        'participant_id',
        'cause_id',
        'source',
        'amount_sen',
        'distance_cm',
        'status',
        'donor_display_name',
        'is_anonymous_public',
        'encouragement_message',
        'is_message_approved',
        'external_order_id',
        'provider_bill_code',
        'provider_reference',
        'received_at',
        'finalised_at',
        'entered_by_user_id',
        'original_contribution_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_sen' => 'integer',
            'distance_cm' => 'integer',
            'is_anonymous_public' => 'boolean',
            'is_message_approved' => 'boolean',
            'received_at' => 'datetime',
            'finalised_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $contribution): void {
            $contribution->distance_cm = JogathonAmount::distanceCmFromSen((int) $contribution->amount_sen);
        });
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where(function (Builder $confirmed): void {
            $confirmed
                ->where(function (Builder $online): void {
                    $online->where('source', self::SOURCE_ONLINE)
                        ->where('status', self::STATUS_SUCCESSFUL);
                })
                ->orWhere(function (Builder $physical): void {
                    $physical->where('source', self::SOURCE_PHYSICAL_CARD)
                        ->where('status', self::STATUS_FINALISED);
                });
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(JogathonCampaign::class, 'campaign_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(JogathonParticipant::class, 'participant_id');
    }

    public function cause(): BelongsTo
    {
        return $this->belongsTo(JogathonCause::class, 'cause_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function originalContribution(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_contribution_id');
    }
}
