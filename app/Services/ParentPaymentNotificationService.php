<?php

namespace App\Services;

use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class ParentPaymentNotificationService
{
    public function __construct(private readonly WhatsAppTacSender $whatsAppTacSender)
    {
    }

    public function sendPaymentReceipt(
        FamilyPaymentTransaction $transaction,
        ?string $phone = null,
        ?string $parentName = null
    ): array {
        $transaction->ensureReceiptUuid();

        $targetPhone = trim((string) ($phone ?: $transaction->payer_phone));

        if ($targetPhone === '') {
            throw new RuntimeException('No parent phone number available for receipt notification.');
        }

        $greeting = $parentName ? "Assalamualaikum {$parentName}," : 'Assalamualaikum,';

        $lines = [
            $greeting,
            '',
            $transaction->status === 'success'
                ? 'Pembayaran PIBG anda telah diterima.'
                : 'Maklumat pembayaran PIBG anda telah dikemaskini.',
            'Kod keluarga: '.$transaction->familyBilling->family_code,
            'Jumlah: RM'.number_format((float) $transaction->amount, 2),
            'Order ID: '.$transaction->external_order_display,
        ];

        if ($transaction->provider_invoice_no) {
            $lines[] = 'Invoice: '.$transaction->provider_invoice_no;
        }

        if ($transaction->paid_at) {
            $lines[] = 'Tarikh bayar: '.$transaction->paid_at->format('d/m/Y h:i A');
        }

        $lines[] = 'Resit web: '.$this->receiptUrl($transaction);

        $delivery = $this->whatsAppTacSender->sendMessage($targetPhone, implode("\n", $lines));

        $transaction->forceFill([
            'receipt_message_id' => $delivery['message_id'] ?? null,
            'receipt_notified_at' => now(),
        ])->save();

        return $delivery;
    }

    public function receiptUrl(FamilyPaymentTransaction $transaction): string
    {
        $transaction->ensureReceiptUuid();

        return route('receipts.show', ['receiptUuid' => $transaction->receipt_uuid]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sendTeacherClassNotifications(FamilyPaymentTransaction $transaction): array
    {
        $billing = $transaction->familyBilling()->first();
        if (! $billing) {
            return [];
        }

        $classNames = Student::query()
            ->where('family_code', $billing->family_code)
            ->where('billing_year', (int) $billing->billing_year)
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->pluck('class_name')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($classNames->isEmpty()) {
            return [];
        }

        $teachers = User::query()
            ->where('role', 'teacher')
            ->where('is_active', true)
            ->where('receive_whatsapp_notifications', true)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereIn('class_name', $classNames->all())
            ->orderBy('name')
            ->get();

        if ($teachers->isEmpty()) {
            return [];
        }

        $childrenByClass = Student::query()
            ->where('family_code', $billing->family_code)
            ->where('billing_year', (int) $billing->billing_year)
            ->whereIn('class_name', $classNames->all())
            ->orderBy('full_name')
            ->get()
            ->groupBy(fn (Student $student) => (string) $student->class_name)
            ->map(fn (Collection $rows) => $rows->pluck('full_name')->map(fn ($name) => trim((string) $name))->filter()->values());

        $receiptUrl = $this->receiptUrl($transaction);
        $deliveries = [];

        foreach ($teachers as $teacher) {
            $teacherClass = (string) $teacher->class_name;
            $childNames = $childrenByClass->get($teacherClass, collect())
                ->take(5)
                ->implode(', ');

            $lines = [
                'Assalamualaikum '.$teacher->name.',',
                '',
                'Makluman bayaran PIBG berjaya diterima untuk kelas anda.',
                'Kod keluarga: '.$billing->family_code,
                'Tahun bil: '.$billing->billing_year,
                'Kelas: '.$teacherClass,
                'Jumlah: RM'.number_format((float) $transaction->amount, 2),
                'Order ID: '.$transaction->external_order_display,
            ];

            if ($childNames !== '') {
                $lines[] = 'Murid: '.$childNames;
            }

            $lines[] = 'Resit web: '.$receiptUrl;

            $delivery = $this->whatsAppTacSender->sendMessage((string) $teacher->phone, implode("\n", $lines));

            $deliveries[] = [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->name,
                'teacher_phone' => $teacher->phone,
                'teacher_class' => $teacherClass,
                'delivery' => $delivery,
            ];
        }

        return $deliveries;
    }
}
