<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCampaignSetting extends Model
{
    public const VISIBILITY_ALL = 'all';
    public const VISIBILITY_SOCIAL_TAG = 'social_tag';

    protected $fillable = [
        'campaign_name',
        'is_active',
        'allow_single_payment',
        'allow_split_payment',
        'allow_split_2',
        'split_2_visibility',
        'split_2_social_tag',
        'split_2_social_tag_id',
        'allow_split_3',
        'split_3_visibility',
        'split_3_social_tag',
        'split_3_social_tag_id',
        'effective_from',
        'effective_until',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_single_payment' => 'boolean',
            'allow_split_payment' => 'boolean',
            'allow_split_2' => 'boolean',
            'allow_split_3' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function split2SocialTag(): BelongsTo
    {
        return $this->belongsTo(SocialTag::class, 'split_2_social_tag_id');
    }

    public function split3SocialTag(): BelongsTo
    {
        return $this->belongsTo(SocialTag::class, 'split_3_social_tag_id');
    }

    public function scopeActiveWindow(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $nested): void {
                $nested->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function (Builder $nested): void {
                $nested->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', now());
            });
    }
}
