<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FamilyPaymentTransaction extends Model
{
    protected $fillable = [
        'family_billing_id',
        'user_id',
        'payment_provider',
        'external_order_id',
        'receipt_uuid',
        'provider_bill_code',
        'provider_ref_no',
        'provider_invoice_no',
        'receipt_message_id',
        'amount',
        'fee_amount_paid',
        'donation_amount',
        'payer_name',
        'payer_email',
        'payer_phone',
        'status',
        'return_status',
        'status_reason',
        'paid_at',
        'receipt_notified_at',
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
            'receipt_notified_at' => 'datetime',
            'raw_return' => 'array',
            'raw_callback' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            if (! $transaction->receipt_uuid) {
                $transaction->receipt_uuid = (string) Str::uuid();
            }
        });
    }

    public function ensureReceiptUuid(): void
    {
        if ($this->receipt_uuid) {
            return;
        }

        $this->forceFill([
            'receipt_uuid' => (string) Str::uuid(),
        ])->save();
    }

    public function familyBilling(): BelongsTo
    {
        return $this->belongsTo(FamilyBilling::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getCreatedAtForDisplayAttribute(): ?Carbon
    {
        return $this->normalizeForDisplay($this->created_at);
    }

    public function getPaidAtForDisplayAttribute(): ?Carbon
    {
        return $this->normalizeForDisplay($this->paid_at);
    }

    private function normalizeForDisplay(DateTimeInterface|string|null $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        $timezone = config('app.timezone', 'Asia/Kuala_Lumpur');
        $localized = Carbon::parse($value)->timezone($timezone);

        // Backward-compatibility: older rows may be stored with an 8-hour offset.
        if ($localized->greaterThan(now($timezone)->addMinutes(5))) {
            return $localized->subHours(8);
        }

        return $localized;
    }
}
