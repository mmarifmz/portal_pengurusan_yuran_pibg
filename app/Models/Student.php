<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'student_no',
    'family_code',
    'ssp_student_id',
    'full_name',
    'class_name',
    'is_duplicate',
    'parent_name',
    'parent_phone',
    'parent_email',
    'total_fee',
    'paid_amount',
    'status',
    'billing_year',
    'annual_fee',
    'import_raw_line',
    'is_b40',
    'is_kwap',
    'is_rmt',
])]
class Student extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_duplicate' => 'boolean',
            'total_fee' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'annual_fee' => 'decimal:2',
            'is_b40' => 'boolean',
            'is_kwap' => 'boolean',
            'is_rmt' => 'boolean',
        ];
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return (float) $this->total_fee - (float) $this->paid_amount;
    }

    public function getFullNameAttribute(?string $value): string
    {
        return $this->normalizeDisplayName($value);
    }

    public function getParentNameAttribute(?string $value): string
    {
        return $this->normalizeDisplayName($value);
    }

    private function normalizeDisplayName(?string $value): string
    {
        $name = trim((string) $value);

        if ($name === '') {
            return '';
        }

        return mb_strtoupper($name);
    }
}
