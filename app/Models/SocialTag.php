<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'sort_order',
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
            'sort_order' => 'integer',
        ];
    }

    public function familyBillings(): BelongsToMany
    {
        return $this->belongsToMany(FamilyBilling::class, 'family_social_tags')
            ->withTimestamps();
    }

    public function split2Campaigns(): HasMany
    {
        return $this->hasMany(PaymentCampaignSetting::class, 'split_2_social_tag_id');
    }

    public function split3Campaigns(): HasMany
    {
        return $this->hasMany(PaymentCampaignSetting::class, 'split_3_social_tag_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
