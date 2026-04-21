<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\Student;
use App\Support\ParentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ParentDashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $parentUser = $request->user();
        $parentPhone = $parentUser?->phone;
        $billingYear = now()->year;
        $isTesterMode = (bool) $parentUser?->isParentTester();

        $accessibleFamilyCodes = $this->resolveAccessibleFamilyCodes($parentPhone);

        $children = Student::query()
            ->whereIn('family_code', $accessibleFamilyCodes)
            ->orderBy('full_name')
            ->get();

        $familyCodes = $children
            ->pluck('family_code')
            ->filter(fn ($code) => filled($code))
            ->unique()
            ->values();

        $studentIds = $children->pluck('id')->filter()->values();
        $childNames = $children
            ->pluck('full_name')
            ->map(fn ($name) => $this->normalizeNameForLegacyMatch((string) $name))
            ->filter()
            ->unique()
            ->values();

        $familyBillings = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->whereIn('family_code', $familyCodes)
            ->orderBy('family_code')
            ->get();

        $latestPaidYear = FamilyBilling::query()
            ->whereIn('family_code', $familyCodes)
            ->where(function ($query) {
                $query->where('status', 'paid')
                    ->orWhereColumn('paid_amount', '>=', 'fee_amount');
            })
            ->max('billing_year');

        $hasAdditionalDonationForLatestPaidYear = false;
        if ($latestPaidYear) {
            $hasAdditionalDonationForLatestPaidYear = FamilyPaymentTransaction::query()
                ->where('status', 'success')
                ->where('donation_amount', '>', 0)
                ->whereHas('familyBilling', fn ($query) => $query
                    ->whereIn('family_code', $familyCodes)
                    ->where('billing_year', (int) $latestPaidYear))
                ->exists();
        }

        $legacyPayments = LegacyStudentPayment::query()
            ->where(function ($nested) use ($familyCodes, $studentIds) {
                $nested->whereIn('family_code', $familyCodes);

                if ($studentIds->isNotEmpty()) {
                    $nested->orWhereIn('student_id', $studentIds->all());
                }
            })
            ->where('payment_status', 'paid')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(function (LegacyStudentPayment $payment) use ($familyCodes, $studentIds, $childNames): bool {
                if ($payment->student_id !== null && $studentIds->contains((int) $payment->student_id)) {
                    return true;
                }

                if (! $familyCodes->contains((string) $payment->family_code)) {
                    return false;
                }

                $legacyName = $this->normalizeNameForLegacyMatch((string) $payment->student_name);
                return $legacyName !== '' && $childNames->contains($legacyName);
            })
            ->values();

        return view('parent.dashboard', [
            'children' => $children,
            'familyBillings' => $familyBillings,
            'billingYear' => $billingYear,
            'isTesterMode' => $isTesterMode,
            'totalOutstanding' => (float) $familyBillings->sum(fn (FamilyBilling $billing): float => $billing->outstanding_amount),
            'latestPaidYear' => $latestPaidYear ? (int) $latestPaidYear : null,
            'hasAdditionalDonationForLatestPaidYear' => $hasAdditionalDonationForLatestPaidYear,
            'legacyPayments' => $legacyPayments,
            'legacyPaidTotal' => (float) $legacyPayments->sum('amount_paid'),
            'legacyDonationTotal' => (float) $legacyPayments->sum('donation_amount'),
        ]);
    }

    private function normalizeNameForLegacyMatch(string $name): string
    {
        $value = mb_strtoupper(trim($name));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim((string) $value);
    }

    /**
     * @return Collection<int, string>
     */
    private function resolveAccessibleFamilyCodes(?string $phone): Collection
    {
        $normalizedPhone = ParentPhone::normalizeForMatch((string) $phone);

        if ($normalizedPhone === '') {
            return collect();
        }

        $studentFamilyCodes = Student::query()
            ->whereIn('parent_phone', ParentPhone::variants((string) $phone))
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
