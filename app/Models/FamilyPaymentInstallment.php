<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyPaymentInstallment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_INITIATED = 'initiated';
    public const STATUS_REDIRECTED = 'redirected';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'family_payment_plan_id',
        'family_billing_id',
        'installment_no',
        'amount',
        'status',
        'billcode',
        'toyyibpay_refno',
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

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(FamilyPaymentPlan::class, 'family_payment_plan_id');
    }

    public function familyBilling(): BelongsTo
    {
        return $this->belongsTo(FamilyBilling::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FamilyPaymentTransaction::class, 'family_payment_installment_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((string) $this->status) {
            self::STATUS_PAID => 'Selesai Dibayar',
            self::STATUS_FAILED => 'Gagal',
            self::STATUS_CANCELLED => 'Dibatalkan',
            self::STATUS_REDIRECTED => 'Menunggu Bayaran',
            self::STATUS_INITIATED => 'Bil Dijana',
            default => 'Belum Dibayar',
        };
    }
}
