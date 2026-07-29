<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Models\UserChangeAudit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManualFamilyPaymentService
{
    public function __construct(private readonly FamilyPaymentPlanService $paymentPlanService) {}

    public function complete(
        FamilyBilling $familyBilling,
        User $parentUser,
        User $adminUser,
        CarbonInterface $paidAt,
        string $paymentReference,
        string $verificationNote
    ): FamilyPaymentTransaction {
        if (! UserChangeAudit::tableIsAvailable()) {
            throw ValidationException::withMessages([
                'manual_payment' => 'Manual payment completion requires the latest audit migration.',
            ]);
        }

        return DB::transaction(function () use (
            $familyBilling,
            $parentUser,
            $adminUser,
            $paidAt,
            $paymentReference,
            $verificationNote
        ): FamilyPaymentTransaction {
            $billing = FamilyBilling::query()
                ->lockForUpdate()
                ->findOrFail($familyBilling->id);

            $outstandingAmount = round((float) $billing->outstanding_amount, 2);

            if ($outstandingAmount <= 0) {
                throw ValidationException::withMessages([
                    'manual_payment' => 'This family payment is already complete.',
                ]);
            }

            $before = [
                'family_billing_id' => $billing->id,
                'family_code' => (string) $billing->family_code,
                'billing_year' => (int) $billing->billing_year,
                'fee_amount' => (float) $billing->fee_amount,
                'paid_amount' => (float) $billing->paid_amount,
                'outstanding_amount' => $outstandingAmount,
                'status' => (string) $billing->status,
            ];

            $this->supersedeOpenGatewayTransactions($billing);

            $externalOrderId = $this->makeExternalOrderId($billing, $adminUser);
            $transaction = FamilyPaymentTransaction::query()->create([
                'family_billing_id' => $billing->id,
                'user_id' => $parentUser->id,
                'payment_provider' => 'manual',
                'external_order_id' => $externalOrderId,
                'provider_ref_no' => $paymentReference,
                'amount' => $outstandingAmount,
                'fee_amount_paid' => $outstandingAmount,
                'donation_amount' => 0,
                'payer_name' => (string) $parentUser->name,
                'payer_email' => (string) ($parentUser->email ?? ''),
                'payer_phone' => (string) ($parentUser->phone ?? ''),
                'status' => 'success',
                'return_status' => 'manual',
                'status_reason' => $verificationNote,
                'paid_at' => $paidAt,
                'raw_return' => [
                    'source' => 'parent_management_manual_completion',
                    'admin_user_id' => $adminUser->id,
                    'payment_reference' => $paymentReference,
                    'verification_note' => $verificationNote,
                    'recorded_at' => now()->toIso8601String(),
                ],
            ]);

            PaymentAllocation::query()->create([
                'family_payment_transaction_id' => $transaction->id,
                'family_billing_id' => $billing->id,
                'order_id' => $externalOrderId,
                'allocation_type' => PaymentAllocation::TYPE_YURAN,
                'amount' => $outstandingAmount,
                'status' => PaymentAllocation::STATUS_PAID,
                'paid_at' => $paidAt,
            ]);

            $this->completeActivePlan($billing, $paidAt);

            $billing->forceFill([
                'paid_amount' => (float) $billing->fee_amount,
                'status' => 'paid',
            ])->save();

            UserChangeAudit::query()->create([
                'admin_user_id' => $adminUser->id,
                'affected_user_id' => $parentUser->id,
                'field_changed' => 'manual_payment_completed',
                'old_value' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'new_value' => json_encode([
                    'family_payment_transaction_id' => $transaction->id,
                    'paid_amount' => (float) $billing->fee_amount,
                    'outstanding_amount' => 0,
                    'status' => 'paid',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'changed_at' => now(),
                'meta' => [
                    'family_billing_id' => $billing->id,
                    'family_code' => (string) $billing->family_code,
                    'billing_year' => (int) $billing->billing_year,
                    'payment_reference' => $paymentReference,
                    'paid_at' => $paidAt->toIso8601String(),
                    'verification_note' => $verificationNote,
                ],
            ]);

            return $transaction->fresh(['familyBilling', 'allocations']);
        });
    }

    private function supersedeOpenGatewayTransactions(FamilyBilling $billing): void
    {
        $transactions = FamilyPaymentTransaction::query()
            ->with('allocations')
            ->where('family_billing_id', $billing->id)
            ->whereNotIn('status', ['success', 'superseded'])
            ->lockForUpdate()
            ->get();

        foreach ($transactions as $transaction) {
            $transaction->forceFill([
                'status' => 'superseded',
                'return_status' => 'superseded',
                'status_reason' => 'superseded_by_manual_payment',
            ])->save();

            $transaction->allocations()
                ->where('status', PaymentAllocation::STATUS_PENDING)
                ->update([
                    'status' => PaymentAllocation::STATUS_CANCELLED,
                ]);
        }
    }

    private function completeActivePlan(FamilyBilling $billing, CarbonInterface $paidAt): void
    {
        $plan = FamilyPaymentPlan::query()
            ->where('family_billing_id', $billing->id)
            ->where('status', '!=', FamilyPaymentPlan::STATUS_CANCELLED)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $plan) {
            return;
        }

        $plan->installments()
            ->where('status', '!=', FamilyPaymentInstallment::STATUS_PAID)
            ->update([
                'status' => FamilyPaymentInstallment::STATUS_PAID,
                'paid_at' => $paidAt,
                'updated_at' => now(),
            ]);

        $this->paymentPlanService->recalculatePlan($plan);
    }

    private function makeExternalOrderId(FamilyBilling $billing, User $adminUser): string
    {
        do {
            $externalOrderId = sprintf(
                'MANUAL-%s-%d-%d-%s',
                now()->format('ymd'),
                $billing->id,
                $adminUser->id,
                Str::upper(Str::random(6))
            );
        } while (FamilyPaymentTransaction::query()->where('external_order_id', $externalOrderId)->exists());

        return $externalOrderId;
    }
}
