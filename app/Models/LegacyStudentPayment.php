<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyStudentPayment extends Model
{
    protected $fillable = [
        'student_id',
        'student_no',
        'family_code',
        'student_name',
        'class_name',
        'source_year',
        'payment_status',
        'amount_due',
        'amount_paid',
        'donation_amount',
        'payment_reference',
        'paid_at',
        'raw_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_year' => 'integer',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'donation_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

