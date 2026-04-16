<?php

namespace App\Http\Controllers;

use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function show(string $receiptUuid): View
    {
        $transaction = FamilyPaymentTransaction::query()
            ->with('familyBilling')
            ->where('receipt_uuid', $receiptUuid)
            ->firstOrFail();

        $familyChildren = Student::query()
            ->where('family_code', $transaction->familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        $receiptUrl = route('receipts.show', ['receiptUuid' => $transaction->receipt_uuid]);
        $portalUrl = route('home');

        return view('parent.receipt-web', [
            'transaction' => $transaction,
            'familyChildren' => $familyChildren,
            'portalUrl' => $portalUrl,
            'teacherShareUrl' => $this->buildTeacherShareUrl($transaction, $receiptUrl),
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
            'Order ID: '.$transaction->external_order_id,
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

}

