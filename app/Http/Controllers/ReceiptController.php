<?php

namespace App\Http\Controllers;

use App\Models\FamilyPaymentTransaction;
use App\Models\SiteSetting;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function show(Request $request, string $receiptUuid): View
    {
        $transaction = FamilyPaymentTransaction::query()
            ->with('familyBilling')
            ->where('receipt_uuid', $receiptUuid)
            ->firstOrFail();

        $isPublicReceipt = $request->user() === null;
        $receiptUrl = route('receipts.show', ['receiptUuid' => $transaction->receipt_uuid]);
        $portalUrl = route('home');
        $backUrl = $isPublicReceipt
            ? $portalUrl
            : $this->resolvePreviousInAppUrl($request, $receiptUrl, $portalUrl);

        $familyChildren = Student::query()
            ->where('family_code', $transaction->familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        $familyChildren = $familyChildren->map(function (Student $child) use ($isPublicReceipt) {
            $child->setAttribute(
                'display_name',
                $isPublicReceipt ? $this->maskName((string) $child->full_name) : (string) $child->full_name
            );
            $child->setAttribute(
                'display_student_no',
                $isPublicReceipt ? 'Dilindungi' : ((string) $child->student_no ?: '-')
            );

            return $child;
        });

        return view('parent.receipt-web', [
            'transaction' => $transaction,
            'familyChildren' => $familyChildren,
            'portalUrl' => $portalUrl,
            'backUrl' => $backUrl,
            'backLabel' => $isPublicReceipt ? 'Back to portal' : 'Back',
            'isPublicReceipt' => $isPublicReceipt,
            'displayOrderId' => (string) ($transaction->external_order_id ?: $transaction->external_order_display),
            'displayPayerEmail' => $isPublicReceipt
                ? $this->maskEmail($transaction->payer_email)
                : ($transaction->payer_email ?: '-'),
            'displayPayerPhone' => $isPublicReceipt
                ? $this->maskPhone($transaction->payer_phone)
                : ($transaction->payer_phone ?: '-'),
            'displayInvoiceNo' => $isPublicReceipt
                ? $this->maskMiddle((string) ($transaction->provider_invoice_no ?: 'Belum dijana'), 3, 2)
                : ($transaction->provider_invoice_no ?: 'Belum dijana'),
            'teacherShareUrl' => $this->buildTeacherShareUrl($transaction, $receiptUrl),
            'schoolLogoUrl' => SiteSetting::schoolLogoUrl(),
        ]);
    }

    private function buildTeacherShareUrl(FamilyPaymentTransaction $transaction, string $receiptUrl): string
    {
        $phone = $this->normalizeWaPhone((string) config('services.teacher_whatsapp_phone', '60123103205'));

        $message = implode("\n", [
            'Assalamualaikum guru,',
            '',
            'Saya ingin kongsi resit bayaran PIBG:',
            'Kod keluarga: '.($transaction->familyBilling->family_code ?? '-'),
            'Jumlah: RM'.number_format((float) $transaction->amount, 2),
            'Order ID: '.$transaction->external_order_display,
            'Resit web: '.$receiptUrl,
        ]);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    private function normalizeWaPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '60123103205';
        }

        if (str_starts_with($digits, '60')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '6'.$digits;
        }

        if (str_starts_with($digits, '1')) {
            return '60'.$digits;
        }

        return $digits;
    }

    private function resolvePreviousInAppUrl(Request $request, string $currentUrl, string $fallbackUrl): string
    {
        $previousUrl = url()->previous();
        $host = $request->getSchemeAndHttpHost();

        if (
            is_string($previousUrl)
            && $previousUrl !== ''
            && $previousUrl !== $currentUrl
            && str_starts_with($previousUrl, $host)
        ) {
            return $previousUrl;
        }

        return $fallbackUrl;
    }

    private function maskName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '-';
        }

        $length = mb_strlen($trimmed);
        if ($length <= 3) {
            return mb_substr($trimmed, 0, 1).str_repeat('*', max(0, $length - 1));
        }

        $visible = max(2, (int) floor($length * 0.55));

        return mb_substr($trimmed, 0, $visible).str_repeat('*', max(1, $length - $visible));
    }

    private function maskEmail(?string $email): string
    {
        $value = trim((string) $email);
        if ($value === '' || ! str_contains($value, '@')) {
            return 'Dilindungi';
        }

        [$local, $domain] = explode('@', $value, 2);
        $localMasked = mb_strlen($local) <= 2
            ? mb_substr($local, 0, 1).'*'
            : mb_substr($local, 0, 2).str_repeat('*', max(2, mb_strlen($local) - 2));

        return $localMasked.'@'.$domain;
    }

    private function maskPhone(?string $phone): string
    {
        $value = trim((string) $phone);
        if ($value === '') {
            return 'Dilindungi';
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return 'Dilindungi';
        }

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        return substr($digits, 0, max(1, strlen($digits) - 4)).'****';
    }

    private function maskMiddle(string $value, int $prefix = 2, int $suffix = 2): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 'Dilindungi';
        }

        $length = mb_strlen($trimmed);
        if ($length <= ($prefix + $suffix + 2)) {
            return mb_substr($trimmed, 0, 1).str_repeat('*', max(2, $length - 1));
        }

        $start = mb_substr($trimmed, 0, $prefix);
        $end = mb_substr($trimmed, -$suffix);

        return $start.str_repeat('*', max(4, $length - $prefix - $suffix)).$end;
    }
}

