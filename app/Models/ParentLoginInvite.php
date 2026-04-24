<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentLoginInvite extends Model
{
    protected $fillable = [
        'family_billing_id',
        'user_id',
        'phone',
        'normalized_phone',
        'token',
        'expires_at',
        'sent_at',
        'used_at',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function familyBilling(): BelongsTo
    {
        return $this->belongsTo(FamilyBilling::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
