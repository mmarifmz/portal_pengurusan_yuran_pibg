<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use Illuminate\Support\Facades\DB;

class FamilyPaymentSettlementService
{
    public function __construct(private readonly FamilyPaymentPlanService $planService)
    {
    }

    /**
     * @param  array<string, mixed>|null  $gatewayRecord
     */
    public function synchronizeSuccessfulPayment(
        FamilyPaymentTransaction $transaction,
        ?array $gatewayRecord = null,
        string $gatewayReason = ''
    ): void {
        $paidAmount = $this->normalizeGatewayAmount($gatewayRecord['billpaymentAmount'] ?? $transaction->amount);
        $invoiceNo = (string) ($gatewayRecord['billpaymentInvoiceNo'] ?? '');
        $paymentDate = (string) ($gatewayRecord['billPaymentDate'] ?? $gatewayRecord['billpaymentDate'] ?? '');

        DB::transaction(function () use ($transaction, $paidAmount, $invoiceNo, $paymentDate, $gatewayReason): void {
            $freshTransaction = FamilyPaymentTransaction::query()
                ->with(['familyBilling', 'installment.paymentPlan'])
                ->lockForUpdate()
                ->find($transaction->id);

            if (! $freshTransaction || ($freshTransaction->status === 'success' && $freshTransaction->paid_at !== null)) {
                return;
            }

            if ($freshTransaction->installment) {
                $this->settleInstallmentTransaction($freshTransaction, $paidAmount, $invoiceNo, $paymentDate, $gatewayReason);

                return;
            }

            $this->settleLegacyTransaction($freshTransaction, $paidAmount, $invoiceNo, $paymentDate, $gatewayReason);
        });
    }

    private function settleInstallmentTransaction(
        FamilyPaymentTransaction $transaction,
        float $paidAmount,
        string $invoiceNo,
        string $paymentDate,
        string $gatewayReason
    ): void {
        $installment = FamilyPaymentInstallment::query()
            ->with('paymentPlan.familyBilling')
            ->lockForUpdate()
            ->find($transaction->family_payment_installment_id);

        if (! $installment) {
            $this->settleLegacyTransaction($transaction, $paidAmount, $invoiceNo, $paymentDate, $gatewayReason);

            return;
        }

        $plan = $installment->paymentPlan;
        $billing = $plan?->familyBilling;
        $feePaid = min((float) $installment->amount, $paidAmount);
        $donation = max(0, $paidAmount - $feePaid);

        $installment->forceFill([
            'status' => FamilyPaymentInstallment::STATUS_PAID,
            'billcode' => $transaction->provider_bill_code ?: $installment->billcode,
            'toyyibpay_refno' => $transaction->provider_ref_no ?: $installment->toyyibpay_refno,
            'paid_at' => $transaction->paid_at ?? now(),
        ])->save();

        $transaction->forceFill([
            'status' => 'success',
            'return_status' => 'successful',
            'provider_invoice_no' => filled($invoiceNo) ? $invoiceNo : $transaction->provider_invoice_no,
            'amount' => $paidAmount,
            'fee_amount_paid' => $feePaid,
            'donation_amount' => $donation,
            'paid_at' => $transaction->paid_at ?? now(),
            'status_reason' => filled($paymentDate)
                ? "Paid at {$paymentDate}"
                : ($gatewayReason !== '' ? $gatewayReason : $transaction->status_reason),
        ])->save();

        if ($plan) {
            $this->planService->recalculatePlan($plan);
        } elseif ($billing instanceof FamilyBilling) {
            $billing->forceFill([
                'paid_amount' => min((float) $billing->fee_amount, (float) $billing->paid_amount + $feePaid),
                'status' => ((float) $billing->paid_amount + $feePaid) >= (float) $billing->fee_amount ? 'paid' : 'partial',
            ])->save();
        }
    }

    private function settleLegacyTransaction(
        FamilyPaymentTransaction $transaction,
        float $paidAmount,
        string $invoiceNo,
        string $paymentDate,
        string $gatewayReason
    ): void {
        $billing = FamilyBilling::query()->lockForUpdate()->find($transaction->family_billing_id);

        if (! $billing) {
            return;
        }

        $feeOutstanding = max(0, (float) $billing->fee_amount - (float) $billing->paid_amount);
        $feeOutstandingAtCheckout = max(0, (float) data_get($transaction->raw_return, 'outstanding_at_checkout', $feeOutstanding));
        $feeOutstanding = min($feeOutstanding, $feeOutstandingAtCheckout);
        $feePaid = min($feeOutstanding, $paidAmount);
        $donation = max(0, $paidAmount - $feePaid);

        $billing->forceFill([
            'paid_amount' => min((float) $billing->fee_amount, (float) $billing->paid_amount + $feePaid),
            'status' => ((float) $billing->paid_amount + $feePaid) >= (float) $billing->fee_amount ? 'paid' : 'partial',
        ])->save();

        $transaction->forceFill([
            'status' => 'success',
            'return_status' => 'successful',
            'provider_invoice_no' => filled($invoiceNo) ? $invoiceNo : $transaction->provider_invoice_no,
            'amount' => $paidAmount,
            'fee_amount_paid' => $feePaid,
            'donation_amount' => $donation,
            'paid_at' => $transaction->paid_at ?? now(),
            'status_reason' => filled($paymentDate)
                ? "Paid at {$paymentDate}"
                : ($gatewayReason !== '' ? $gatewayReason : $transaction->status_reason),
        ])->save();
    }

    private function normalizeGatewayAmount(mixed $amount): float
    {
        $stringAmount = trim((string) $amount);

        if ($stringAmount === '') {
            return 0.0;
        }

        if (str_contains($stringAmount, '.')) {
            return (float) $stringAmount;
        }

        $numeric = (float) $stringAmount;

        return $numeric > 1000 ? round($numeric / 100, 2) : $numeric;
    }
}
