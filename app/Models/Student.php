<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable([
    'student_no',
    'family_code',
    'ssp_student_id',
    'full_name',
    'class_name',
    'is_duplicate',
    'parent_name',
    'parent_phone',
    'parent_email',
    'total_fee',
    'paid_amount',
    'status',
    'billing_year',
    'annual_fee',
    'import_raw_line',
    'is_b40',
    'is_kwap',
    'is_rmt',
    'transferred_at',
    'transferred_by',
    'transfer_note',
])]
class Student extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRANSFERRED = 'transferred';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_duplicate' => 'boolean',
            'total_fee' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'annual_fee' => 'decimal:2',
            'is_b40' => 'boolean',
            'is_kwap' => 'boolean',
            'is_rmt' => 'boolean',
            'transferred_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('status')
                ->orWhere('status', '!=', self::STATUS_TRANSFERRED);
        });
    }

    public function scopeTransferred(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TRANSFERRED);
    }

    public static function activeFamilyCodesForYear(int $billingYear): Collection
    {
        return static::query()
            ->active()
            ->where('billing_year', $billingYear)
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->pluck('family_code')
            ->map(fn ($familyCode): string => trim((string) $familyCode))
            ->filter()
            ->unique()
            ->values();
    }

    public function transferredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function isTransferred(): bool
    {
        return (string) $this->getAttribute('status') === self::STATUS_TRANSFERRED;
    }

    public function statusLabel(): string
    {
        return $this->isTransferred() ? 'Telah Berpindah' : 'Aktif';
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return (float) $this->total_fee - (float) $this->paid_amount;
    }

    public function getFullNameAttribute(?string $value): string
    {
        return $this->normalizeDisplayName($value);
    }

    public function getParentNameAttribute(?string $value): string
    {
        return $this->normalizeDisplayName($value);
    }

    private function normalizeDisplayName(?string $value): string
    {
        $name = trim((string) $value);

        if ($name === '') {
            return '';
        }

        return mb_strtoupper($name);
    }
}
