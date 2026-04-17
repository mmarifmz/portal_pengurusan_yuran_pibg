<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyBillingPhone extends Model
{
    public const MAX_PHONES_PER_FAMILY = 5;

    protected $fillable = [
        'family_billing_id',
        'phone',
        'normalized_phone',
    ];

    public function familyBilling(): BelongsTo
    {
        return $this->belongsTo(FamilyBilling::class);
    }
}
