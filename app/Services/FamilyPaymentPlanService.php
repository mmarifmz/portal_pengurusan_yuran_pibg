<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FamilyPaymentPlanService
{
    /**
     * @return array<int, array{type: string, label: string, amounts: array<int, float>, total: float}>
     */
    public function availablePlans(float $totalAmount = 100.0, ?array $allowedPlanTypes = null): array
    {
        $planTypes = $allowedPlanTypes ?? [
            FamilyPaymentPlan::PLAN_FULL,
            FamilyPaymentPlan::PLAN_TWO_TIMES,
            FamilyPaymentPlan::PLAN_THREE_TIMES,
        ];

        return collect($planTypes)->map(function (string $planType) use ($totalAmount): array {
            $amounts = $this->installmentAmountsFor($planType, $totalAmount);

            return [
                'type' => $planType,
                'label' => $this->planLabel($planType),
                'amounts' => $amounts,
                'total' => round(array_sum($amounts), 2),
            ];
        })->all();
    }

    public function planLabel(string $planType): string
    {
        return match ($planType) {
            FamilyPaymentPlan::PLAN_TWO_TIMES => 'Bayar 2 Kali',
            FamilyPaymentPlan::PLAN_THREE_TIMES => 'Bayar 3 Kali',
            default => 'Bayar Penuh',
        };
    }

    /**
     * @return array<int, float>
     */
    public function installmentAmountsFor(string $planType, float $totalAmount): array
    {
        $normalizedTotal = round(max(0, $totalAmount), 2);

        return match ($planType) {
            FamilyPaymentPlan::PLAN_TWO_TIMES => $this->splitInHalf($normalizedTotal),
            FamilyPaymentPlan::PLAN_THREE_TIMES => $this->splitInThree($normalizedTotal),
            FamilyPaymentPlan::PLAN_FULL => [$normalizedTotal],
            default => throw new InvalidArgumentException('Unsupported payment plan type.'),
        };
    }

    public function createPlan(FamilyBilling $familyBilling, string $planType): FamilyPaymentPlan
    {
        $existingPlan = $familyBilling->paymentPlan()->with('installments')->first();

        if ($existingPlan) {
            return $existingPlan;
        }

        $amounts = $this->installmentAmountsFor($planType, (float) $familyBilling->fee_amount);
        $totalAmount = round(array_sum($amounts), 2);

        return DB::transaction(function () use ($familyBilling, $planType, $amounts, $totalAmount): FamilyPaymentPlan {
            $plan = FamilyPaymentPlan::query()->create([
                'family_billing_id' => $familyBilling->id,
                'plan_type' => $planType,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'balance_amount' => $totalAmount,
                'status' => FamilyPaymentPlan::STATUS_PENDING,
                'selected_at' => now(),
            ]);

            foreach ($amounts as $index => $amount) {
                FamilyPaymentInstallment::query()->create([
                    'family_payment_plan_id' => $plan->id,
                    'family_billing_id' => $familyBilling->id,
                    'installment_no' => $index + 1,
                    'amount' => $amount,
                    'status' => FamilyPaymentInstallment::STATUS_PENDING,
                ]);
            }

            return $plan->load('installments');
        });
    }

    public function canChangePlan(FamilyPaymentPlan $plan): bool
    {
        $plan->loadMissing('installments.transactions');

        if ((float) $plan->paid_amount > 0) {
            return false;
        }

        if ((string) $plan->status === FamilyPaymentPlan::STATUS_PAID) {
            return false;
        }

        $hasPaidInstallment = $plan->installments
            ->contains(fn (FamilyPaymentInstallment $installment): bool => $installment->status === FamilyPaymentInstallment::STATUS_PAID);

        if ($hasPaidInstallment) {
            return false;
        }

        return ! $plan->installments
            ->flatMap(fn (FamilyPaymentInstallment $installment) => $installment->transactions)
            ->contains(fn (FamilyPaymentTransaction $transaction): bool => strtolower((string) $transaction->status) === 'success');
    }

    public function cancelPlanForParentChange(FamilyPaymentPlan $plan): FamilyPaymentPlan
    {
        $plan->loadMissing('familyBilling', 'installments.transactions');

        return DB::transaction(function () use ($plan): FamilyPaymentPlan {
            foreach ($plan->installments as $installment) {
                if ($installment->status === FamilyPaymentInstallment::STATUS_PAID) {
                    continue;
                }

                $installment->forceFill([
                    'status' => FamilyPaymentInstallment::STATUS_CANCELLED,
                ])->save();

                $installment->transactions
                    ->filter(fn (FamilyPaymentTransaction $transaction): bool => strtolower((string) $transaction->status) !== 'success')
                    ->each(function (FamilyPaymentTransaction $transaction): void {
                        $transaction->forceFill([
                            'status' => 'cancelled',
                            'return_status' => 'parent cancel',
                            'status_reason' => 'parent_changed_payment_plan',
                            'raw_return' => array_merge($transaction->raw_return ?? [], [
                                'parent_action' => 'changed_payment_plan',
                            ]),
                        ])->save();
                    });
            }

            $plan->forceFill([
                'status' => FamilyPaymentPlan::STATUS_CANCELLED,
            ])->save();

            $plan->familyBilling?->forceFill([
                'paid_amount' => 0,
                'status' => 'unpaid',
            ])->save();

            return $plan->fresh(['familyBilling', 'installments.transactions']);
        });
    }

    public function recalculatePlan(FamilyPaymentPlan $plan): FamilyPaymentPlan
    {
        $plan->loadMissing('installments', 'familyBilling');

        $paidAmount = round((float) $plan->installments
            ->where('status', FamilyPaymentInstallment::STATUS_PAID)
            ->sum(fn (FamilyPaymentInstallment $installment): float => (float) $installment->amount), 2);
        $balanceAmount = round(max(0, (float) $plan->total_amount - $paidAmount), 2);
        $status = $paidAmount <= 0
            ? FamilyPaymentPlan::STATUS_PENDING
            : ($balanceAmount <= 0 ? FamilyPaymentPlan::STATUS_PAID : FamilyPaymentPlan::STATUS_PARTIAL);

        $plan->forceFill([
            'paid_amount' => $paidAmount,
            'balance_amount' => $balanceAmount,
            'status' => $status,
        ])->save();

        $billing = $plan->familyBilling;

        if ($billing) {
            $billingPaidAmount = min((float) $billing->fee_amount, $paidAmount);
            $billingStatus = $billingPaidAmount <= 0
                ? 'unpaid'
                : ($billingPaidAmount >= (float) $billing->fee_amount ? 'paid' : 'partial');

            $billing->forceFill([
                'paid_amount' => $billingPaidAmount,
                'status' => $billingStatus,
            ])->save();
        }

        return $plan->fresh(['installments', 'familyBilling']);
    }

    public function validateInstallmentCanBePaid(FamilyPaymentInstallment $installment): ?string
    {
        $installment->loadMissing('paymentPlan.installments');

        if ($installment->status === FamilyPaymentInstallment::STATUS_PAID) {
            return 'Ansuran ini telah selesai dibayar.';
        }

        if ($installment->status === FamilyPaymentInstallment::STATUS_CANCELLED) {
            return 'Ansuran ini telah dibatalkan. Sila pilih semula pelan bayaran.';
        }

        $plan = $installment->paymentPlan;

        if (! $plan) {
            return 'Pelan bayaran untuk ansuran ini tidak ditemui.';
        }

        if ($plan->status === FamilyPaymentPlan::STATUS_CANCELLED) {
            return 'Pelan bayaran ini telah dibatalkan. Sila pilih semula pelan bayaran.';
        }

        if ($plan->status === FamilyPaymentPlan::STATUS_PAID) {
            return 'Semua ansuran untuk keluarga ini telah selesai dibayar.';
        }

        if ($plan->allow_admin_override) {
            return null;
        }

        $previousUnpaid = $plan->installments
            ->where('installment_no', '<', $installment->installment_no)
            ->first(fn (FamilyPaymentInstallment $row): bool => $row->status !== FamilyPaymentInstallment::STATUS_PAID);

        if ($previousUnpaid) {
            return 'Sila selesaikan ansuran terdahulu sebelum meneruskan bayaran ini.';
        }

        return null;
    }

    public function syncInstallmentAttemptStatus(FamilyPaymentTransaction $transaction, string $transactionStatus): void
    {
        $transaction->loadMissing('installment.paymentPlan');

        $installment = $transaction->installment;

        if (
            ! $installment
            || $installment->status === FamilyPaymentInstallment::STATUS_PAID
            || $installment->status === FamilyPaymentInstallment::STATUS_CANCELLED
            || $installment->paymentPlan?->status === FamilyPaymentPlan::STATUS_CANCELLED
        ) {
            return;
        }

        $normalizedStatus = strtolower(trim($transactionStatus));
        $installmentStatus = match ($normalizedStatus) {
            'success' => FamilyPaymentInstallment::STATUS_PAID,
            'failed' => FamilyPaymentInstallment::STATUS_FAILED,
            'cancelled', 'canceled' => FamilyPaymentInstallment::STATUS_CANCELLED,
            default => filled($transaction->provider_bill_code)
                ? FamilyPaymentInstallment::STATUS_REDIRECTED
                : FamilyPaymentInstallment::STATUS_INITIATED,
        };

        $installment->forceFill([
            'status' => $installmentStatus,
            'billcode' => $transaction->provider_bill_code ?: $installment->billcode,
            'toyyibpay_refno' => $transaction->provider_ref_no ?: $installment->toyyibpay_refno,
        ])->save();

        if ($installment->paymentPlan) {
            $this->recalculatePlan($installment->paymentPlan);
        }
    }

    /**
     * @return array<int, float>
     */
    private function splitInHalf(float $totalAmount): array
    {
        $first = round($totalAmount / 2, 2);
        $second = round($totalAmount - $first, 2);

        return [$first, $second];
    }

    /**
     * @return array<int, float>
     */
    private function splitInThree(float $totalAmount): array
    {
        if (abs($totalAmount - 100.0) < 0.01) {
            return [40.0, 30.0, 30.0];
        }

        $first = round($totalAmount * 0.4, 2);
        $second = round($totalAmount * 0.3, 2);
        $third = round($totalAmount - $first - $second, 2);

        return [$first, $second, $third];
    }
}
