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

        $message = $this->buildPaymentReceiptMessage($transaction, $parentName);
        $delivery = $this->whatsAppTacSender->sendMessage($targetPhone, $message);

        $transaction->forceFill([
            'receipt_message_id' => $delivery['message_id'] ?? null,
            'receipt_notified_at' => now(),
        ])->save();

        return $delivery;
    }

    public function buildPaymentReceiptMessagePreview(
        FamilyPaymentTransaction $transaction,
        ?string $parentName = null
    ): string {
        return $this->buildPaymentReceiptMessage($transaction, $parentName);
    }

    private function buildPaymentReceiptMessage(FamilyPaymentTransaction $transaction, ?string $parentName = null): string
    {
        $receiptUrl = $this->receiptUrl($transaction);
        $maxChars = max(200, (int) config('services.parent_receipt_whatsapp_max_chars', 1000));
        $familyCode = (string) ($transaction->familyBilling->family_code ?? '-');
        $amountText = 'RM'.number_format((float) $transaction->amount, 2);
        $orderIdText = (string) $transaction->external_order_display;
        $invoiceText = trim((string) $transaction->provider_invoice_no);
        $paidAtText = $transaction->paid_at_for_display?->format('d/m/Y, h:i A');

        $headerLine = trim((string) $parentName) !== ''
            ? 'Assalamualaikum dan Salam Sejahtera, '.trim((string) $parentName)
            : 'Assalamualaikum dan Salam Sejahtera,';

        $fullLines = [
            $headerLine,
            '',
            'Pembayaran PIBG anda telah berjaya diterima. Terima kasih atas komitmen anda 😊',
            '',
            'Berikut adalah maklumat pembayaran:',
            '',
            '• Kod Keluarga: '.$familyCode,
            '• Jumlah: '.$amountText,
            '• Order ID: '.$orderIdText,
        ];

        if ($invoiceText !== '') {
            $fullLines[] = '• Invoice: '.$invoiceText;
        }

        if ($paidAtText) {
            $fullLines[] = '• Tarikh Bayar: '.$paidAtText;
        }

        $fullLines = array_merge($fullLines, [
            '',
            'Resit Web:',
            $receiptUrl,
            '',
            '📱 Sila login ke dashboard ibu bapa menggunakan nombor telefon ini.',
            'Di dalam dashboard, anda boleh:',
            '',
            '• Menyemak takwim sekolah',
            '• Melihat resit pembayaran tahun lepas dan tahun semasa',
            '• Membuat sumbangan tambahan kepada PIBG SSP',
            '',
            'Sekali lagi, terima kasih atas sokongan anda kepada PIBG SSP.',
        ]);

        $fullMessage = implode("\n", $fullLines);
        if (mb_strlen($fullMessage) <= $maxChars) {
            return $fullMessage;
        }

        $compactLines = [
            'Assalamualaikum dan Salam Sejahtera,',
            'Pembayaran PIBG anda telah berjaya diterima.',
            'Kod Keluarga: '.$familyCode,
            'Jumlah: '.$amountText,
            'Order ID: '.$orderIdText,
        ];

        if ($invoiceText !== '') {
            $compactLines[] = 'Invoice: '.$invoiceText;
        }

        if ($paidAtText) {
            $compactLines[] = 'Tarikh Bayar: '.$paidAtText;
        }

        $compactLines[] = 'Resit Web: '.$receiptUrl;
        $compactLines[] = 'Terima kasih atas sokongan anda kepada PIBG SSP.';

        $compactMessage = implode("\n", $compactLines);
        if (mb_strlen($compactMessage) <= $maxChars) {
            return $compactMessage;
        }

        $suffix = '...';
        $trimLength = max(1, $maxChars - mb_strlen($suffix));

        return mb_substr($compactMessage, 0, $trimLength).$suffix;
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
