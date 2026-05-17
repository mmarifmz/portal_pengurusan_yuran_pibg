<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyPaymentPlan extends Model
{
    public const PLAN_FULL = 'full';
    public const PLAN_TWO_TIMES = 'two_times';
    public const PLAN_THREE_TIMES = 'three_times';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'family_billing_id',
        'plan_type',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'allow_admin_override',
        'selected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'allow_admin_override' => 'boolean',
            'selected_at' => 'datetime',
        ];
    }

    public function familyBilling(): BelongsTo
    {
        return $this->belongsTo(FamilyBilling::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(FamilyPaymentInstallment::class)->orderBy('installment_no');
    }

    public function paidInstallments(): HasMany
    {
        return $this->hasMany(FamilyPaymentInstallment::class)->where('status', FamilyPaymentInstallment::STATUS_PAID);
    }

    public function getPlanLabelAttribute(): string
    {
        return match ((string) $this->plan_type) {
            self::PLAN_TWO_TIMES => 'Bayar 2 Kali',
            self::PLAN_THREE_TIMES => 'Bayar 3 Kali',
            default => 'Bayar Penuh',
        };
    }

    public function getPaidInstallmentsSummaryAttribute(): string
    {
        $paidCount = $this->relationLoaded('installments')
            ? $this->installments->where('status', FamilyPaymentInstallment::STATUS_PAID)->count()
            : $this->paidInstallments()->count();
        $totalCount = $this->relationLoaded('installments')
            ? $this->installments->count()
            : $this->installments()->count();

        return sprintf('%d/%d paid', $paidCount, $totalCount);
    }
}
