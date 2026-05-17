<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use App\Models\PaymentAllocation;
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
                ->with(['familyBilling', 'installment.paymentPlan', 'allocations'])
                ->lockForUpdate()
                ->find($transaction->id);

            if (
                ! $freshTransaction
                || ($freshTransaction->status === 'success' && $freshTransaction->paid_at !== null)
                || in_array(strtolower((string) $freshTransaction->status), ['cancelled', 'superseded'], true)
            ) {
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
        [$feePaid, $donation] = $this->resolveAllocationBreakdown(
            $transaction,
            min((float) $installment->amount, $paidAmount),
            max(0, $paidAmount - min((float) $installment->amount, $paidAmount))
        );

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

        $this->markAllocationsPaid($transaction);

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
        [$feePaid, $donation] = $this->resolveAllocationBreakdown(
            $transaction,
            min($feeOutstanding, $paidAmount),
            max(0, $paidAmount - min($feeOutstanding, $paidAmount))
        );

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

        $this->markAllocationsPaid($transaction);
    }

    public function syncTransactionAllocationStatus(FamilyPaymentTransaction $transaction): void
    {
        $transaction->loadMissing('allocations');

        if ($transaction->allocations->isEmpty()) {
            return;
        }

        $normalizedStatus = strtolower(trim((string) $transaction->status));
        $allocationStatus = match ($normalizedStatus) {
            'success' => PaymentAllocation::STATUS_PAID,
            'failed' => PaymentAllocation::STATUS_FAILED,
            'cancelled', 'superseded' => PaymentAllocation::STATUS_CANCELLED,
            default => PaymentAllocation::STATUS_PENDING,
        };

        foreach ($transaction->allocations as $allocation) {
            if ($allocation->status === PaymentAllocation::STATUS_PAID && $allocationStatus !== PaymentAllocation::STATUS_PAID) {
                continue;
            }

            $allocation->forceFill([
                'status' => $allocationStatus,
                'billcode' => $transaction->provider_bill_code ?: $allocation->billcode,
                'order_id' => $transaction->external_order_id ?: $allocation->order_id,
                'paid_at' => $allocationStatus === PaymentAllocation::STATUS_PAID
                    ? ($transaction->paid_at ?? now())
                    : $allocation->paid_at,
            ])->save();
        }
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function resolveAllocationBreakdown(FamilyPaymentTransaction $transaction, float $fallbackFeePaid, float $fallbackDonation): array
    {
        $transaction->loadMissing('allocations');

        if ($transaction->allocations->isEmpty()) {
            return [round($fallbackFeePaid, 2), round($fallbackDonation, 2)];
        }

        $feePaid = (float) $transaction->allocations
            ->where('allocation_type', PaymentAllocation::TYPE_YURAN)
            ->sum('amount');
        $donation = (float) $transaction->allocations
            ->where('allocation_type', PaymentAllocation::TYPE_SUMBANGAN_TAMBAHAN)
            ->sum('amount');

        return [round($feePaid, 2), round($donation, 2)];
    }

    private function markAllocationsPaid(FamilyPaymentTransaction $transaction): void
    {
        $transaction->loadMissing('allocations');

        foreach ($transaction->allocations as $allocation) {
            $allocation->forceFill([
                'status' => PaymentAllocation::STATUS_PAID,
                'billcode' => $transaction->provider_bill_code ?: $allocation->billcode,
                'order_id' => $transaction->external_order_id ?: $allocation->order_id,
                'paid_at' => $transaction->paid_at ?? now(),
            ])->save();
        }
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
