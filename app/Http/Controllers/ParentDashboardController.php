<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $parentPhone = $request->user()?->phone;
        $billingYear = now()->year;

        $children = Student::query()
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
            ->whereIn('family_code', $familyCodes)
            ->orderBy('family_code')
            ->get();

        return view('parent.dashboard', [
            'children' => $children,
            'familyBillings' => $familyBillings,
            'billingYear' => $billingYear,
            'totalOutstanding' => (float) $familyBillings->sum(fn (FamilyBilling $billing): float => $billing->outstanding_amount),
        ]);
    }
}