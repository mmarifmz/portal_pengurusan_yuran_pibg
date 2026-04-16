<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\SchoolCalendarEvent;
use App\Models\Student;
use Illuminate\View\View;

class TeacherDashboardController extends Controller
{
    public function index(): View
    {
        $billingYear = now()->year;

        $totalStudents = Student::query()->count();
        $totalFamilies = Student::query()
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->distinct('family_code')
            ->count('family_code');

        $billings = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->get();

        $totalBilled = (float) $billings->sum('fee_amount');
        $totalCollected = (float) $billings->sum('paid_amount');
        $totalOutstanding = (float) $billings->sum(fn (FamilyBilling $billing): float => $billing->outstanding_amount);
        $paidFamilies = $billings->filter(fn (FamilyBilling $billing): bool => $billing->outstanding_amount <= 0)->count();

        $recentBillings = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->latest()
            ->take(15)
            ->get();

        $calendarEvents = SchoolCalendarEvent::query()
            ->orderBy('start_date')
            ->orderBy('sort_order')
            ->get();

        return view('teacher.dashboard', [
            'billingYear' => $billingYear,
            'totalStudents' => $totalStudents,
            'totalFamilies' => $totalFamilies,
            'totalBilled' => $totalBilled,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'paidFamilies' => $paidFamilies,
            'recentBillings' => $recentBillings,
            'calendarEvents' => $calendarEvents,
        ]);
    }
}
