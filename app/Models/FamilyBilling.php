<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyBilling extends Model
{
    protected $fillable = [
        'family_code',
        'billing_year',
        'fee_amount',
        'paid_amount',
        'status',
        'due_date',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_year' => 'integer',
            'fee_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function getOutstandingAmountAttribute(): float
    {
        return max(0, (float) $this->fee_amount - (float) $this->paid_amount);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(FamilyPaymentTransaction::class);
    }
}
