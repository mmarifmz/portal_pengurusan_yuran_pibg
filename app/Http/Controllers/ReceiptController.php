<?php

namespace App\Http\Controllers;

use App\Models\FamilyPaymentTransaction;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Services\ParentAccessLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ParentAccessLogService $parentAccessLogService
    ) {
    }

    public function show(Request $request, string $receiptUuid): View
    {
        $transaction = FamilyPaymentTransaction::query()
            ->with(['familyBilling', 'installment.paymentPlan.installments'])
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

        if ($request->user()?->hasRole('parent')) {
            $request->session()->put('active_portal_space', 'parent');
            $this->parentAccessLogService->log($request, 'viewed_receipt', [
                'user' => $request->user(),
                'family_billing' => $transaction->familyBilling,
                'space_key' => 'parent',
                'meta' => ['receipt_uuid' => $receiptUuid, 'source' => 'web_receipt'],
            ]);
        }

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
            'receiptContext' => $this->buildReceiptContext($transaction),
            'teacherShareUrl' => $this->buildTeacherShareUrl($transaction, $receiptUrl),
            'schoolLogoUrl' => SiteSetting::schoolLogoUrl(),
        ]);
    }

    private function buildTeacherShareUrl(FamilyPaymentTransaction $transaction, string $receiptUrl): string
    {
        $phone = $this->normalizeWaPhone((string) config('services.teacher_whatsapp_phone', '60123103205'));
        $receiptContext = $this->buildReceiptContext($transaction);

        $lines = [
            'Assalamualaikum guru,',
            '',
            'Saya ingin kongsi resit bayaran PIBG:',
            'Kod keluarga: '.($transaction->familyBilling->family_code ?? '-'),
            'Yuran Dibayar: RM'.number_format((float) ($receiptContext['yuran_paid_this_transaction'] ?? 0), 2),
            'Sumbangan Tambahan: RM'.number_format((float) ($receiptContext['donation_paid_this_transaction'] ?? 0), 2),
            'Jumlah Dibayar: RM'.number_format((float) $transaction->amount, 2),
            'Order ID: '.$transaction->external_order_display,
            'Resit web: '.$receiptUrl,
        ];

        if ($receiptContext['installment_label'] !== null) {
            array_splice($lines, 5, 0, ['Ansuran: '.$receiptContext['installment_label']]);
        }

        $message = implode("\n", $lines);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    /**
     * @return array{
     *   has_installment: bool,
     *   installment_label: string|null,
     *   transaction_amount: float,
     *   yuran_paid_this_transaction: float,
     *   donation_paid_this_transaction: float,
     *   total_paid_to_date: float|null,
     *   remaining_balance: float|null,
     *   payment_status_label: string|null,
     *   fully_paid: bool
     * }
     */
    private function buildReceiptContext(FamilyPaymentTransaction $transaction): array
    {
        $transaction->loadMissing('installment.paymentPlan.installments');

        $installment = $transaction->installment;
        $plan = $installment?->paymentPlan;

        if (! $installment || ! $plan) {
            $billing = $transaction->familyBilling;
            $billingFeeAmount = (float) ($billing?->fee_amount ?? 0);
            $billingPaidAmount = (float) ($billing?->paid_amount ?? 0);
            $yuranPaid = (float) ($transaction->fee_amount_paid ?? min((float) $transaction->amount, $billingFeeAmount));
            $donationPaid = (float) ($transaction->donation_amount ?? max(0, (float) $transaction->amount - $yuranPaid));

            return [
                'has_installment' => false,
                'installment_label' => null,
                'transaction_amount' => round((float) $transaction->amount, 2),
                'yuran_paid_this_transaction' => round($yuranPaid, 2),
                'donation_paid_this_transaction' => round($donationPaid, 2),
                'total_paid_to_date' => round($billingPaidAmount, 2),
                'remaining_balance' => round((float) ($billing?->outstanding_amount ?? 0), 2),
                'payment_status_label' => $billing && $billing->outstanding_amount <= 0 ? 'Selesai Dibayar' : ((float) $billingPaidAmount > 0 ? 'Bayaran Sebahagian' : ucfirst((string) $transaction->status)),
                'fully_paid' => $billing ? (float) $billing->outstanding_amount <= 0 : strtolower((string) $transaction->status) === 'success',
            ];
        }

        return [
            'has_installment' => true,
            'installment_label' => sprintf('Ansuran %d/%d', (int) $installment->installment_no, $plan->installments->count()),
            'transaction_amount' => round((float) $transaction->amount, 2),
            'yuran_paid_this_transaction' => round((float) ($transaction->fee_amount_paid ?? 0), 2),
            'donation_paid_this_transaction' => round((float) ($transaction->donation_amount ?? 0), 2),
            'total_paid_to_date' => round((float) $plan->paid_amount, 2),
            'remaining_balance' => round((float) $plan->balance_amount, 2),
            'payment_status_label' => (float) $plan->balance_amount <= 0 ? 'Selesai Dibayar' : 'Bayaran Sebahagian',
            'fully_paid' => (float) $plan->balance_amount <= 0,
        ];
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

