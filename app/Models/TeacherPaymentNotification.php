<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherPaymentNotification extends Model
{
    protected $table = 'teacher_payment_notifications';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'family_id',
        'student_id',
        'student_name',
        'teacher_id',
        'teacher_name',
        'teacher_phone',
        'class_name',
        'payment_flow_id',
        'order_id',
        'bill_year',
        'receipt_url',
        'pibg_amount',
        'donation_amount',
        'total_amount',
        'message_body',
        'status',
        'attempt_count',
        'idempotency_key',
        'queued_at',
        'processing_at',
        'sent_at',
        'failed_at',
        'cancelled_at',
        'last_error',
        'api_response',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pibg_amount' => 'decimal:2',
            'donation_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'queued_at' => 'datetime',
            'processing_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(FamilyBilling::class, 'family_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function paymentFlow(): BelongsTo
    {
        return $this->belongsTo(FamilyPaymentTransaction::class, 'payment_flow_id');
    }

    public function isQueued(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RETRYING], true);
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSING,
            'processing_at' => now(),
            'attempt_count' => (int) $this->attempt_count + 1,
            'last_error' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>|string|null  $response
     */
    public function markSent(array|string|null $response = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'failed_at' => null,
            'last_error' => null,
            'api_response' => is_array($response)
                ? json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : $response,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'last_error' => $error,
        ])->save();
    }

    public function markRetrying(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_RETRYING,
            'last_error' => $error,
        ])->save();
    }

    public function markCancelled(): void
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ])->save();
    }

    public function resetForRetry(): void
    {
        $this->forceFill([
            'status' => self::STATUS_QUEUED,
            'attempt_count' => 0,
            'queued_at' => now(),
            'processing_at' => null,
            'sent_at' => null,
            'failed_at' => null,
            'cancelled_at' => null,
            'last_error' => null,
            'api_response' => null,
        ])->save();
    }

    public static function labelForStatus(?string $status): string
    {
        return match ((string) $status) {
            self::STATUS_QUEUED => 'Makluman kepada guru kelas sedang menunggu giliran penghantaran.',
            self::STATUS_PROCESSING => 'Makluman sedang diproses untuk dihantar kepada guru kelas.',
            self::STATUS_SENT => 'Makluman telah berjaya dihantar kepada guru kelas.',
            self::STATUS_FAILED => 'Makluman gagal dihantar. Pihak admin boleh membuat penghantaran semula.',
            self::STATUS_RETRYING => 'Makluman sedang dicuba semula.',
            self::STATUS_CANCELLED => 'Makluman telah dibatalkan oleh admin.',
            default => 'Status makluman kepada guru kelas sedang dikemas kini.',
        };
    }
}
