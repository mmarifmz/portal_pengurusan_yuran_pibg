<?php

namespace App\Models;

use Database\Factories\JogathonParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class JogathonParticipant extends Model
{
    /** @use HasFactory<JogathonParticipantFactory> */
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'student_id',
        'public_slug',
        'physical_card_number',
        'public_display_name',
        'class_name_snapshot',
        'target_amount_sen',
        'target_distance_cm',
        'is_eligible',
        'is_published',
        'participation_opt_out',
        'enrolled_at',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'is_eligible' => 'boolean',
            'is_published' => 'boolean',
            'participation_opt_out' => 'boolean',
            'enrolled_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(JogathonCampaign::class, 'campaign_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(JogathonContribution::class, 'participant_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(JogathonParticipantVisit::class, 'participant_id');
    }

    public function publicUrlIdentifier(): string
    {
        $cardNumber = self::normalizePhysicalCardNumber($this->getAttribute('physical_card_number'));

        return $cardNumber ?: (string) $this->public_slug;
    }

    public function publicShortUrl(): ?string
    {
        $cardNumber = self::normalizePhysicalCardNumber($this->getAttribute('physical_card_number'));

        return $cardNumber ? route('jogathon.public.card.show', $cardNumber) : null;
    }

    public static function normalizePhysicalCardNumber(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/\s+/', '-', $normalized) ?: '';

        return $normalized === '' ? null : $normalized;
    }

    public static function hasPhysicalCardNumberColumn(): bool
    {
        try {
            return Schema::hasColumn('jogathon_participants', 'physical_card_number');
        } catch (\Throwable) {
            return false;
        }
    }

    public function isPubliclyVisible(): bool
    {
        return $this->is_eligible
            && $this->is_published
            && ! $this->participation_opt_out
            && $this->withdrawn_at === null;
    }
}
