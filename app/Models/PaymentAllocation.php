<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    public const TYPE_YURAN = 'yuran';
    public const TYPE_SUMBANGAN_TAMBAHAN = 'sumbangan_tambahan';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'family_payment_transaction_id',
        'family_payment_installment_id',
        'family_billing_id',
        'billcode',
        'order_id',
        'allocation_type',
        'amount',
        'status',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FamilyPaymentTransaction::class, 'family_payment_transaction_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(FamilyPaymentInstallment::class, 'family_payment_installment_id');
    }

    public function familyBilling(): BelongsTo
    {
        return $this->belongsTo(FamilyBilling::class);
    }
}
