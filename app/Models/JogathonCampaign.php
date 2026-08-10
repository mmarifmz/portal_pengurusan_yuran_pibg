<?php

namespace App\Models;

use Database\Factories\JogathonCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JogathonCampaign extends Model
{
    /** @use HasFactory<JogathonCampaignFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'default_target_amount_sen',
        'default_target_distance_cm',
        'show_class_publicly',
        'allow_public_indexing',
        'allow_unspecified_cause',
        'year_to_tahap',
        'created_by_user_id',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'show_class_publicly' => 'boolean',
            'allow_public_indexing' => 'boolean',
            'allow_unspecified_cause' => 'boolean',
            'year_to_tahap' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draf',
            self::STATUS_SCHEDULED => 'Dijadualkan',
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_PAUSED => 'Dijeda',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_ARCHIVED => 'Diarkibkan',
        ];
    }

    public function causes(): HasMany
    {
        return $this->hasMany(JogathonCause::class, 'campaign_id')->orderBy('sort_order')->orderBy('id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(JogathonParticipant::class, 'campaign_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(JogathonContribution::class, 'campaign_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(JogathonAudit::class, 'campaign_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isPubliclyAvailable(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_ACTIVE, self::STATUS_COMPLETED], true)
            && $this->archived_at === null;
    }
}
