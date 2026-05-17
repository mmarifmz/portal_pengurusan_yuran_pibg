<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilySocialTag extends Model
{
    protected $fillable = [
        'family_billing_id',
        'social_tag_id',
    ];

    public function familyBilling(): BelongsTo
    {
        return $this->belongsTo(FamilyBilling::class);
    }

    public function socialTag(): BelongsTo
    {
        return $this->belongsTo(SocialTag::class);
    }
}
