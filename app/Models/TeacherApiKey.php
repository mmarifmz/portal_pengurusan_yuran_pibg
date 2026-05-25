<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TeacherApiKey extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'teacher_id',
        'key_hash',
        'key_prefix',
        'last_four',
        'status',
        'last_used_at',
        'total_calls',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'total_calls' => 'integer',
        ];
    }

    public static function generatePlainKey(): string
    {
        return 'pibg_live_'.Str::random(32);
    }

    public static function hashPlainKey(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->whereNull('revoked_at');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ApiAccessLog::class, 'api_key_id');
    }

    public function maskedKey(): string
    {
        return sprintf('%s_%s', $this->key_prefix ?: 'pibg_live', str_repeat('*', 12).$this->last_four);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->revoked_at === null;
    }

    public function revoke(?User $revokedBy = null): void
    {
        if (! $this->isActive()) {
            return;
        }

        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoked_by' => $revokedBy?->id,
        ])->save();
    }
}
