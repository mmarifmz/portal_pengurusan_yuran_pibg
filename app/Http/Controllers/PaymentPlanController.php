<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentPlan;
use App\Models\Student;
use App\Services\FamilyPaymentPlanService;
use App\Support\ParentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PaymentPlanController extends Controller
{
    public function __construct(
        private readonly FamilyPaymentPlanService $paymentPlanService
    ) {
    }

    public function changePlan(Request $request, FamilyPaymentPlan $paymentPlan): RedirectResponse
    {
        $paymentPlan->loadMissing('familyBilling', 'installments.transactions');

        $familyBilling = $paymentPlan->familyBilling;

        abort_unless($familyBilling instanceof FamilyBilling, 404);

        $this->authorizeParentFamilyBilling($request, $familyBilling);

        if (! $this->paymentPlanService->canChangePlan($paymentPlan)) {
            return redirect()
                ->route('parent.payments.checkout', $familyBilling)
                ->withErrors([
                    'payment_plan' => 'Pilihan bayaran tidak boleh ditukar kerana bayaran telah dibuat.',
                ]);
        }

        $this->paymentPlanService->cancelPlanForParentChange($paymentPlan);

        return redirect()
            ->route('parent.payments.review', [
                'familyBilling' => $familyBilling,
                'select_plan' => 1,
            ])
            ->with('status', 'Pilihan bayaran semasa telah dibatalkan. Sila pilih pelan bayaran yang baharu.');
    }

    private function authorizeParentFamilyBilling(Request $request, FamilyBilling $familyBilling): void
    {
        $selectionCompleted = (bool) $request->session()->get('parent_child_selection_completed', false);
        $selectedBillingId = (int) $request->session()->get('parent_selected_family_billing_id', 0);

        if ($request->user()?->isParentTester()) {
            return;
        }

        $ownedFamilyCodes = $this->resolveOwnedFamilyCodes((string) $request->user()?->phone);

        if (! ($selectionCompleted && $selectedBillingId === (int) $familyBilling->id)) {
            abort_unless(
                $ownedFamilyCodes->contains($familyBilling->family_code),
                403,
                'Please select your child from Carian Nama Murid before opening checkout.'
            );

            $request->session()->put('parent_selected_family_billing_id', (int) $familyBilling->id);
            $request->session()->put('parent_child_selection_completed', true);

            return;
        }

        abort_unless($ownedFamilyCodes->contains($familyBilling->family_code), 403, 'Unauthorized family billing access.');
    }

    /**
     * @return Collection<int, string>
     */
    private function resolveOwnedFamilyCodes(string $phone): Collection
    {
        $normalizedPhone = ParentPhone::normalizeForMatch($phone);

        if ($normalizedPhone === '') {
            return collect();
        }

        $studentFamilyCodes = Student::query()
            ->whereIn('parent_phone', ParentPhone::variants($phone))
            ->whereNotNull('family_code')
            ->pluck('family_code');

        $registeredFamilyCodes = FamilyBilling::query()
            ->whereHas('phones', fn ($query) => $query->where('normalized_phone', $normalizedPhone))
            ->pluck('family_code');

        return $studentFamilyCodes
            ->merge($registeredFamilyCodes)
            ->filter()
            ->unique()
            ->values();
    }
}
