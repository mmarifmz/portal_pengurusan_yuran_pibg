<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\Student;
use Illuminate\View\View;

class TeacherRecordsController extends Controller
{
    public function index(): View
    {
        $billingYear = now()->year;

        $students = Student::query()
            ->orderBy('family_code')
            ->orderBy('full_name')
            ->get();

        $familyBillings = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->withCount('students')
            ->orderBy('family_code')
            ->get();

        $studentCount = $students->count();
        $familiesCount = $familyBillings->count();
        $studentsWithoutFamily = $students->filter(fn (Student $student) => blank($student->family_code))->count();
        $totalBilled = (float) $familyBillings->sum('fee_amount');
        $totalCollected = (float) $familyBillings->sum('paid_amount');
        $totalOutstanding = (float) $familyBillings->sum(fn (FamilyBilling $billing): float => $billing->outstanding_amount);
        $familiesPaid = $familyBillings->filter(fn (FamilyBilling $billing): bool => $billing->outstanding_amount <= 0)->count();

        return view('teacher.records', [
            'billingYear' => $billingYear,
            'students' => $students,
            'familyBillings' => $familyBillings,
            'studentCount' => $studentCount,
            'familiesCount' => $familiesCount,
            'studentsWithoutFamily' => $studentsWithoutFamily,
            'totalBilled' => $totalBilled,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'familiesPaid' => $familiesPaid,
        ]);
    }
}
