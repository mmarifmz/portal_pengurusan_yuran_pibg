<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyPaymentTransaction extends Model
{
    protected $fillable = [
        'family_billing_id',
        'user_id',
        'payment_provider',
        'external_order_id',
        'provider_bill_code',
        'provider_ref_no',
        'provider_invoice_no',
        'amount',
        'fee_amount_paid',
        'donation_amount',
        'payer_email',
        'payer_phone',
        'status',
        'status_reason',
        'paid_at',
        'raw_return',
        'raw_callback',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount_paid' => 'decimal:2',
            'donation_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'raw_return' => 'array',
            'raw_callback' => 'array',
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
