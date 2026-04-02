<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingSetupController extends Controller
{
    public function setupCurrentYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'billing_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
        ]);

        $billingYear = (int) ($validated['billing_year'] ?? now()->year);

        $familyCodes = Student::query()
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->distinct()
            ->pluck('family_code');

        $created = 0;

        foreach ($familyCodes as $familyCode) {
            $billing = FamilyBilling::query()->firstOrCreate(
                [
                    'family_code' => $familyCode,
                    'billing_year' => $billingYear,
                ],
                [
                    'fee_amount' => 100,
                    'paid_amount' => 0,
                    'status' => 'unpaid',
                ],
            );

            if ($billing->wasRecentlyCreated) {
                $created++;
            }
        }

        return back()->with('status', "Billing setup completed for {$billingYear}. {$created} family billing rows created.");
    }
}