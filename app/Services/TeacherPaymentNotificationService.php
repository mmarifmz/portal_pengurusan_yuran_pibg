<?php

namespace App\Services;

use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Models\TeacherPaymentNotification;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;

class TeacherPaymentNotificationService
{
    public function __construct(
        private readonly TeacherPaymentNotificationMessageBuilder $messageBuilder,
        private readonly ParentAccountService $parentAccountService
    ) {
    }

    /**
     * @return array{
     *   notifications:\Illuminate\Support\Collection<int, \App\Models\TeacherPaymentNotification>,
     *   created_count:int,
     *   duplicate_count:int,
     *   duplicate:bool,
     *   summary:array{status:string,label:string,count:int}|null
     * }
     */
    public function shareReceiptToTeachers(FamilyPaymentTransaction $transaction, ?User $parentUser): array
    {
        $transaction->loadMissing(['familyBilling', 'installment.paymentPlan.installments']);

        if ((string) $transaction->status !== 'success') {
            throw new DomainException('Makluman hanya boleh dihantar untuk bayaran yang telah berjaya.');
        }

        $billing = $transaction->familyBilling;
        if (! $billing) {
            throw new DomainException('Rekod keluarga untuk bayaran ini tidak ditemui.');
        }

        if ($parentUser && ! $parentUser->isParent()) {
            throw new DomainException('Hanya ibu bapa atau penjaga boleh berkongsi resit ini.');
        }

        if ($parentUser) {
            $accessibleFamilyCodes = $this->parentAccountService
                ->accessibleFamilyCodesForUser($parentUser)
                ->all();

            if ($accessibleFamilyCodes !== [] && ! in_array((string) $billing->family_code, $accessibleFamilyCodes, true)) {
                throw new DomainException('Anda tidak mempunyai akses untuk berkongsi resit ini.');
            }
        }

        $targets = $this->resolveTargets($transaction);
        $readyTargets = $targets['ready'];
        $errors = $targets['errors'];

        if ($readyTargets->isEmpty()) {
            throw new DomainException($errors->first() ?: 'Guru kelas tidak dapat dipastikan untuk resit ini.');
        }

        $created = collect();
        $duplicates = 0;

        foreach ($readyTargets as $target) {
            $idempotencyKey = $this->makeIdempotencyKey(
                (int) $billing->id,
                (int) $transaction->id,
                (int) ($target['teacher_id'] ?? 0),
                (string) ($target['teacher_phone'] ?? '')
            );

            $existing = TeacherPaymentNotification::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing && $existing->created_at?->gte(now()->subMinutes(10))) {
                $duplicates++;

                continue;
            }

            if ($existing) {
                $duplicates++;

                continue;
            }

            $context = [
                'teacher_name' => $target['teacher_name'],
                'family_code' => (string) $billing->family_code,
                'bill_year' => (string) $billing->billing_year,
                'class_name' => $target['class_name'],
                'student_names' => $target['student_names'],
                'order_id' => (string) $transaction->external_order_display,
                'receipt_url' => route('receipts.show', $transaction->receipt_uuid),
                'pibg_amount' => $this->pibgAmount($transaction),
                'donation_amount' => $this->donationAmount($transaction),
                'total_amount' => round((float) $transaction->amount, 2),
                'is_instalment' => (bool) $transaction->installment,
                'is_payment_complete' => $this->isPaymentComplete($transaction),
            ];

            $notification = TeacherPaymentNotification::query()->create([
                'family_id' => $billing->id,
                'student_id' => $target['primary_student_id'],
                'student_name' => implode(', ', $target['student_names']),
                'teacher_id' => $target['teacher_id'],
                'teacher_name' => $target['teacher_name'],
                'teacher_phone' => $target['teacher_phone'],
                'class_name' => $target['class_name'],
                'payment_flow_id' => $transaction->id,
                'order_id' => (string) $transaction->external_order_id,
                'bill_year' => (string) $billing->billing_year,
                'receipt_url' => route('receipts.show', $transaction->receipt_uuid),
                'pibg_amount' => $this->pibgAmount($transaction),
                'donation_amount' => $this->donationAmount($transaction),
                'total_amount' => round((float) $transaction->amount, 2),
                'message_body' => $this->messageBuilder->build($context),
                'status' => TeacherPaymentNotification::STATUS_QUEUED,
                'idempotency_key' => $idempotencyKey,
                'queued_at' => now(),
                'created_by' => $parentUser ? 'parent:'.$parentUser->id : 'system',
            ]);

            $created->push($notification);
        }

        return [
            'notifications' => $created,
            'created_count' => $created->count(),
            'duplicate_count' => $duplicates,
            'duplicate' => $created->isEmpty() && $duplicates > 0,
            'summary' => $this->summaryForTransaction($transaction),
        ];
    }

    /**
     * @return array{status:string,label:string,count:int}|null
     */
    public function summaryForTransaction(FamilyPaymentTransaction $transaction): ?array
    {
        $notifications = TeacherPaymentNotification::query()
            ->where('payment_flow_id', $transaction->id)
            ->orderByDesc('id')
            ->get();

        if ($notifications->isEmpty()) {
            return null;
        }

        $status = $this->aggregateStatus($notifications);

        return [
            'status' => $status,
            'label' => TeacherPaymentNotification::labelForStatus($status),
            'count' => $notifications->count(),
        ];
    }

    /**
     * @return array{ready:Collection<int, array<string, mixed>>,errors:Collection<int, string>}
     */
    private function resolveTargets(FamilyPaymentTransaction $transaction): array
    {
        $billing = $transaction->familyBilling;
        $billingYear = (int) ($billing?->billing_year ?? now()->year);

        $students = Student::query()
            ->active()
            ->where('family_code', (string) ($billing?->family_code ?? ''))
            ->where(function ($query) use ($billingYear): void {
                $query->whereNull('billing_year')
                    ->orWhere('billing_year', $billingYear);
            })
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get();

        if ($students->isEmpty()) {
            return [
                'ready' => collect(),
                'errors' => collect(['Tiada murid ditemui bagi rekod keluarga ini.']),
            ];
        }

        $errors = collect();
        $ready = collect();

        $students
            ->groupBy(fn (Student $student): string => trim((string) $student->class_name))
            ->each(function (Collection $classStudents, string $className) use (&$errors, &$ready): void {
                if ($className === '') {
                    $errors->push('Kelas murid tidak ditemui untuk salah satu rekod keluarga ini.');

                    return;
                }

                $teacher = $this->resolveTeacherForClass($className);
                if (! $teacher) {
                    $errors->push("Tiada guru kelas ditetapkan untuk kelas {$className}.");

                    return;
                }

                $teacherPhone = trim((string) $teacher->phone);
                if ($teacherPhone === '') {
                    $errors->push('Nombor telefon guru kelas belum didaftarkan. Sila hubungi pihak sekolah.');

                    return;
                }

                $ready->push([
                    'class_name' => $className,
                    'teacher_id' => $teacher->id,
                    'teacher_name' => (string) $teacher->name,
                    'teacher_phone' => $teacherPhone,
                    'primary_student_id' => $classStudents->first()?->id,
                    'student_names' => $classStudents
                        ->pluck('full_name')
                        ->map(fn ($name): string => trim((string) $name))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ]);
            });

        return [
            'ready' => $ready->values(),
            'errors' => $errors->values(),
        ];
    }

    private function resolveTeacherForClass(string $className): ?User
    {
        return User::query()
            ->withAnyRole(['teacher', 'super_teacher'])
            ->where('is_active', true)
            ->where('class_name', $className)
            ->get(['id', 'name', 'phone', 'role', 'class_name', 'receive_whatsapp_notifications'])
            ->sortBy(fn (User $teacher): string => sprintf(
                '%d-%d-%s',
                $teacher->receive_whatsapp_notifications ? 0 : 1,
                $teacher->role === 'teacher' ? 0 : 1,
                (string) $teacher->name
            ))
            ->first();
    }

    private function isPaymentComplete(FamilyPaymentTransaction $transaction): bool
    {
        $transaction->loadMissing(['familyBilling', 'installment.paymentPlan']);

        if ($transaction->installment && $transaction->installment->paymentPlan) {
            return (float) $transaction->installment->paymentPlan->balance_amount <= 0;
        }

        return (float) ($transaction->familyBilling?->outstanding_amount ?? 0) <= 0;
    }

    private function pibgAmount(FamilyPaymentTransaction $transaction): float
    {
        $feePaid = (float) ($transaction->fee_amount_paid ?? 0);

        if ($feePaid > 0) {
            return round($feePaid, 2);
        }

        $billingFee = (float) ($transaction->familyBilling?->fee_amount ?? 0);

        return round(min((float) $transaction->amount, $billingFee > 0 ? $billingFee : (float) $transaction->amount), 2);
    }

    private function donationAmount(FamilyPaymentTransaction $transaction): float
    {
        $donation = (float) ($transaction->donation_amount ?? 0);
        if ($donation > 0) {
            return round($donation, 2);
        }

        return round(max(0, (float) $transaction->amount - $this->pibgAmount($transaction)), 2);
    }

    private function makeIdempotencyKey(int $familyId, int $paymentFlowId, int $teacherId, string $teacherPhone = ''): string
    {
        return sha1(implode('|', [
            $familyId,
            $paymentFlowId,
            $teacherId > 0 ? $teacherId : $teacherPhone,
        ]));
    }

    /**
     * @param  Collection<int, TeacherPaymentNotification>  $notifications
     */
    private function aggregateStatus(Collection $notifications): string
    {
        $statuses = $notifications->pluck('status')->filter()->values();

        if ($statuses->contains(TeacherPaymentNotification::STATUS_PROCESSING)) {
            return TeacherPaymentNotification::STATUS_PROCESSING;
        }

        if ($statuses->contains(TeacherPaymentNotification::STATUS_QUEUED)) {
            return TeacherPaymentNotification::STATUS_QUEUED;
        }

        if ($statuses->contains(TeacherPaymentNotification::STATUS_RETRYING)) {
            return TeacherPaymentNotification::STATUS_RETRYING;
        }

        if ($statuses->contains(TeacherPaymentNotification::STATUS_FAILED)) {
            return TeacherPaymentNotification::STATUS_FAILED;
        }

        if ($statuses->every(fn (?string $status): bool => $status === TeacherPaymentNotification::STATUS_CANCELLED)) {
            return TeacherPaymentNotification::STATUS_CANCELLED;
        }

        return TeacherPaymentNotification::STATUS_SENT;
    }
}
