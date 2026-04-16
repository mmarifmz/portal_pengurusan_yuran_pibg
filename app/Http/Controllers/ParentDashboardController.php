<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\LegacyStudentPayment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $parentUser = $request->user();
        $parentPhone = $parentUser?->phone;
        $billingYear = now()->year;
        $isTesterMode = (bool) $parentUser?->isParentTester();

        $children = $isTesterMode
            ? collect()
            : Student::query()
                ->when($parentPhone, fn ($query) => $query->where('parent_phone', $parentPhone))
                ->orderBy('full_name')
                ->get();

        $familyCodes = $children
            ->pluck('family_code')
            ->filter(fn ($code) => filled($code))
            ->unique()
            ->values();

        $familyBillings = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->when(! $isTesterMode, fn ($query) => $query->whereIn('family_code', $familyCodes))
            ->orderBy('family_code')
            ->get();

        $legacyPayments = LegacyStudentPayment::query()
            ->when(! $isTesterMode, fn ($query) => $query->whereIn('family_code', $familyCodes))
            ->where('payment_status', 'paid')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('parent.dashboard', [
            'children' => $children,
            'familyBillings' => $familyBillings,
            'billingYear' => $billingYear,
            'isTesterMode' => $isTesterMode,
            'totalOutstanding' => (float) $familyBillings->sum(fn (FamilyBilling $billing): float => $billing->outstanding_amount),
            'legacyPayments' => $legacyPayments,
            'legacyPaidTotal' => (float) $legacyPayments->sum('amount_paid'),
            'legacyDonationTotal' => (float) $legacyPayments->sum('donation_amount'),
        ]);
    }
}
