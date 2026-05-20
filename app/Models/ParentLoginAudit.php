<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class ParentLoginAudit extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'normalized_phone',
        'action_type',
        'access_status',
        'page_visited',
        'login_method',
        'ip_address',
        'user_agent',
        'device_browser',
        'family_billing_id',
        'student_id',
        'space_key',
        'logged_in_at',
        'occurred_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public static function tableIsAvailable(): bool
    {
        return Schema::hasTable((new self)->getTable());
    }

    public static function hasAuditColumn(string $column): bool
    {
        return self::tableIsAvailable() && Schema::hasColumn((new self)->getTable(), $column);
    }

    public static function occurrenceColumn(): string
    {
        return self::hasAuditColumn('occurred_at') ? 'occurred_at' : 'logged_in_at';
    }

    public static function orderByMostRecent(Builder $query): Builder
    {
        return $query
            ->orderByDesc(self::occurrenceColumn())
            ->orderByDesc('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function familyBilling(): BelongsTo
    {
        return $this->belongsTo(FamilyBilling::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getOccurredAtForDisplayAttribute(): ?CarbonInterface
    {
        return $this->occurred_at ?? $this->logged_in_at;
    }
}
