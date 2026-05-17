<?php

namespace App\Models;

use App\Models\FamilyPaymentTransaction;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\SocialTag;
use App\Models\Student;
use App\Support\ParentPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FamilyBilling extends Model
{
    protected $fillable = [
        'family_code',
        'billing_year',
        'fee_amount',
        'paid_amount',
        'status',
        'social_tag',
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

    public function paymentPlan(): HasOne
    {
        return $this->hasOne(FamilyPaymentPlan::class);
    }

    public function paymentInstallments(): HasMany
    {
        return $this->hasMany(FamilyPaymentInstallment::class);
    }

    public function socialTags(): BelongsToMany
    {
        return $this->belongsToMany(SocialTag::class, 'family_social_tags')
            ->withTimestamps()
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'family_code', 'family_code');
    }

    public function phones(): HasMany
    {
        return $this->hasMany(FamilyBillingPhone::class);
    }

    public function hasRegisteredPhone(string $phone): bool
    {
        $normalized = ParentPhone::normalizeForMatch($phone);

        if ($normalized === '') {
            return false;
        }

        return $this->phones()
            ->where('normalized_phone', $normalized)
            ->exists();
    }

    public function registerPhone(string $phone): bool
    {
        $sanitized = ParentPhone::sanitizeInput($phone);
        $normalized = ParentPhone::normalizeForMatch($sanitized);

        if ($normalized === '') {
            return false;
        }

        if ($this->phones()->where('normalized_phone', $normalized)->exists()) {
            return true;
        }

        if ($this->phones()->count() >= FamilyBillingPhone::MAX_PHONES_PER_FAMILY) {
            return false;
        }

        $this->phones()->create([
            'phone' => $sanitized,
            'normalized_phone' => $normalized,
        ]);

        return true;
    }
}
