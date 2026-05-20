<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class UserChangeAudit extends Model
{
    protected $fillable = [
        'admin_user_id',
        'affected_user_id',
        'field_changed',
        'old_value',
        'new_value',
        'changed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public static function tableIsAvailable(): bool
    {
        return Schema::hasTable((new self)->getTable());
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function affectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affected_user_id');
    }
}
