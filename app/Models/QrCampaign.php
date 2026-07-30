<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QrCampaign extends Model
{
    public const PURPOSE_PAYMENT = 'payment';

    public const PURPOSE_DONATION = 'donation';

    public const PURPOSE_EVENT = 'event';

    public const PURPOSE_PROGRAMME = 'programme';

    public const DESTINATION_PAYMENT_DIRECTORY = 'payment_directory';

    public const DESTINATION_PARENT_LOGIN = 'parent_login';

    public const DESTINATION_SCHOOL_CALENDAR = 'school_calendar';

    public const DESTINATION_CUSTOM_INTERNAL = 'custom_internal';

    protected $fillable = [
        'name',
        'short_code',
        'purpose',
        'destination_type',
        'destination_path',
        'payment_campaign_setting_id',
        'class_name',
        'location_name',
        'distribution_channel',
        'poster_title',
        'poster_subtitle',
        'call_to_action',
        'is_active',
        'starts_at',
        'ends_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            if ($campaign->short_code) {
                return;
            }

            do {
                $shortCode = Str::lower(Str::random(10));
            } while (self::query()->where('short_code', $shortCode)->exists());

            $campaign->short_code = $shortCode;
        });
    }

    public function scans(): HasMany
    {
        return $this->hasMany(QrCampaignScan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FamilyPaymentTransaction::class);
    }

    public function paymentCampaignSetting(): BelongsTo
    {
        return $this->belongsTo(PaymentCampaignSetting::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $nested): void {
                $nested->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $nested): void {
                $nested->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gte(now()));
    }

    public function shortUrl(): string
    {
        return route('qr-campaigns.redirect', ['qrCampaign' => $this->short_code]);
    }
}
