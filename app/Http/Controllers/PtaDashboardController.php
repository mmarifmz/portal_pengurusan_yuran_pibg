<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\SchoolCalendarEvent;
use Illuminate\View\View;

class PtaDashboardController extends Controller
{
    public function index(): View
    {
        $billingYear = now()->year;

        $billings = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->orderBy('family_code')
            ->get();

        $calendarEvents = SchoolCalendarEvent::query()
            ->orderBy('start_date')
            ->orderBy('sort_order')
            ->get();

        return view('pta.dashboard', [
            'billingYear' => $billingYear,
            'billings' => $billings,
            'totalFamilies' => $billings->count(),
            'totalBilled' => (float) $billings->sum('fee_amount'),
            'totalCollected' => (float) $billings->sum('paid_amount'),
            'totalOutstanding' => (float) $billings->sum(fn (FamilyBilling $billing): float => $billing->outstanding_amount),
            'calendarEvents' => $calendarEvents,
        ]);
    }
}
