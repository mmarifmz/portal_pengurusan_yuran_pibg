<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\SchoolCalendarEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolCalendarPageController extends Controller
{
    public function index(Request $request): View
    {
        $yearOptions = collect()
            ->merge(FamilyBilling::query()->distinct()->pluck('billing_year'))
            ->merge(LegacyStudentPayment::query()->distinct()->pluck('source_year'))
            ->merge([now()->year])
            ->filter(fn ($year) => is_numeric($year))
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values();

        if ($yearOptions->isEmpty()) {
            $yearOptions = collect([now()->year]);
        }

        $selectedDashboardYear = (int) $request->integer('dashboard_year', (int) $yearOptions->first());
        if (! $yearOptions->contains($selectedDashboardYear)) {
            $selectedDashboardYear = (int) $yearOptions->first();
        }

        $legacyPayments = LegacyStudentPayment::query()
            ->where('source_year', $selectedDashboardYear)
            ->where('payment_status', 'paid')
            ->get();

        $familyBillings = FamilyBilling::query()
            ->where('billing_year', $selectedDashboardYear)
            ->get();

        $useLegacyKpiSource = $familyBillings->isEmpty() && $legacyPayments->isNotEmpty();

        if ($useLegacyKpiSource) {
            $calendarPaidCountByDate = $legacyPayments
                ->filter(fn (LegacyStudentPayment $payment) => $payment->paid_at !== null)
                ->groupBy(fn (LegacyStudentPayment $payment) => $payment->paid_at->format('Y-m-d'))
                ->map(fn ($group) => $group->count())
                ->toArray();
        } else {
            $calendarPaidCountByDate = FamilyPaymentTransaction::query()
                ->where('status', 'success')
                ->whereYear('paid_at', $selectedDashboardYear)
                ->whereNotNull('paid_at')
                ->get()
                ->groupBy(fn (FamilyPaymentTransaction $transaction) => $transaction->paid_at->format('Y-m-d'))
                ->map(fn ($group) => $group->count())
                ->toArray();
        }

        $calendarEvents = SchoolCalendarEvent::query()
            ->orderBy('start_date')
            ->orderBy('sort_order')
            ->get();

        return view('school-calendar', [
            'dashboardYearOptions' => $yearOptions->toArray(),
            'selectedDashboardYear' => $selectedDashboardYear,
            'calendarEvents' => $calendarEvents,
            'calendarPaidCountByDate' => $calendarPaidCountByDate,
        ]);
    }
}