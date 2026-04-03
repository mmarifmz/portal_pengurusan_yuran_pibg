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
    'parent_name',
    'parent_phone',
    'parent_email',
    'total_fee',
    'paid_amount',
    'status',
    'billing_year',
    'annual_fee',
    'import_raw_line',
])]
class Student extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_fee' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'annual_fee' => 'decimal:2',
        ];
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return (float) $this->total_fee - (float) $this->paid_amount;
    }
}
