<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeacherRecordsController extends Controller
{
    public function index(Request $request): View
    {
        $billingYear = now()->year;
        $recordFilter = (string) $request->string('record_filter')->toString();
        $selectedClass = trim((string) $request->string('class_name')->toString());

        $students = Student::query()
            ->orderBy('family_code')
            ->orderBy('full_name')
            ->get();

        $availableClasses = Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->values();

        $filteredStudents = $students
            ->when($recordFilter === 'duplicates', fn ($collection) => $collection->filter(fn (Student $student) => $student->is_duplicate))
            ->when($recordFilter === 'without-family', fn ($collection) => $collection->filter(fn (Student $student) => blank($student->family_code)))
            ->when($selectedClass !== '', fn ($collection) => $collection->filter(fn (Student $student) => (string) $student->class_name === $selectedClass))
            ->values();

        $familyBillings = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->withCount('students')
            ->orderBy('family_code')
            ->get();

        $filteredFamilyCodes = $filteredStudents
            ->pluck('family_code')
            ->filter()
            ->unique()
            ->values();

        $filtersActive = $recordFilter !== '' || $selectedClass !== '';
        $filteredFamilyBillings = $filtersActive
            ? $familyBillings
                ->filter(fn (FamilyBilling $billing) => $filteredFamilyCodes->contains($billing->family_code))
                ->values()
            : $familyBillings;

        $studentCount = $students->count();
        $familiesCount = $familyBillings->count();
        $studentsWithoutFamily = $students->filter(fn (Student $student) => blank($student->family_code))->count();
        $duplicateCount = $students->filter(fn (Student $student) => $student->is_duplicate)->count();
        $totalBilled = (float) $familyBillings->sum('fee_amount');
        $totalCollected = (float) $familyBillings->sum('paid_amount');
        $totalOutstanding = (float) $familyBillings->sum(fn (FamilyBilling $billing): float => $billing->outstanding_amount);
        $familiesPaid = $familyBillings->filter(fn (FamilyBilling $billing): bool => $billing->outstanding_amount <= 0)->count();

        $successfulTransactions = FamilyPaymentTransaction::query()
            ->where('status', 'success')
            ->get(['amount', 'fee_amount_paid', 'donation_amount']);

        $yuranCollection = (float) $successfulTransactions
            ->sum(fn (FamilyPaymentTransaction $transaction) => (float) ($transaction->fee_amount_paid ?? 0));

        $sumbanganCollection = (float) $successfulTransactions
            ->sum(function (FamilyPaymentTransaction $transaction): float {
                if ($transaction->donation_amount !== null) {
                    return (float) $transaction->donation_amount;
                }

                $amount = (float) ($transaction->amount ?? 0);
                $feePaid = (float) ($transaction->fee_amount_paid ?? 0);

                return max(0, $amount - $feePaid);
            });

        $registeredParentCount = ParentLoginOtp::query()
            ->whereNotNull('user_id')
            ->whereNotNull('used_at')
            ->distinct('user_id')
            ->count('user_id');

        return view('teacher.records', [
            'billingYear' => $billingYear,
            'students' => $filteredStudents,
            'familyBillings' => $filteredFamilyBillings,
            'studentCount' => $studentCount,
            'familiesCount' => $familiesCount,
            'studentsWithoutFamily' => $studentsWithoutFamily,
            'duplicateCount' => $duplicateCount,
            'totalBilled' => $totalBilled,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'familiesPaid' => $familiesPaid,
            'yuranCollection' => $yuranCollection,
            'sumbanganCollection' => $sumbanganCollection,
            'registeredParentCount' => $registeredParentCount,
            'availableClasses' => $availableClasses,
            'recordFilter' => $recordFilter,
            'selectedClass' => $selectedClass,
            'filtersActive' => $filtersActive,
        ]);
    }

    public function reviewDuplicate(Student $student): View
    {
        abort_unless($student->is_duplicate, 404);

        $matchingStudents = Student::query()
            ->whereRaw('LOWER(full_name) = ?', [strtolower($student->full_name)])
            ->whereRaw('LOWER(COALESCE(class_name, "")) = ?', [strtolower((string) $student->class_name)])
            ->orderBy('family_code')
            ->orderBy('student_no')
            ->get();

        $selectedFamilyStudents = filled($student->family_code)
            ? Student::query()
                ->where('family_code', $student->family_code)
                ->orderBy('student_no')
                ->get()
            : collect([$student]);

        $keptFamilyCodes = $matchingStudents
            ->filter(function (Student $match) use ($student): bool {
                if (filled($student->family_code)) {
                    return filled($match->family_code) && $match->family_code !== $student->family_code;
                }

                return ! $match->is($student) && filled($match->family_code);
            })
            ->pluck('family_code')
            ->filter()
            ->unique()
            ->values();

        $keptFamilyStudents = $keptFamilyCodes->isNotEmpty()
            ? Student::query()
                ->whereIn('family_code', $keptFamilyCodes)
                ->orderBy('family_code')
                ->orderBy('student_no')
                ->get()
            : $matchingStudents
                ->filter(fn (Student $match): bool => ! $match->is($student))
                ->values();

        return view('teacher.review-duplicate', [
            'student' => $student,
            'matchingStudents' => $matchingStudents,
            'selectedFamilyStudents' => $selectedFamilyStudents,
            'keptFamilyStudents' => $keptFamilyStudents,
        ]);
    }

    public function destroyDuplicate(Student $student): RedirectResponse
    {
        abort_unless($student->is_duplicate, 404);

        DB::transaction(function () use ($student): void {
            $matchingStudents = Student::query()
                ->whereRaw('LOWER(full_name) = ?', [strtolower($student->full_name)])
                ->whereRaw('LOWER(COALESCE(class_name, "")) = ?', [strtolower((string) $student->class_name)])
                ->orderBy('id')
                ->get();

            if (filled($student->family_code)) {
                FamilyBilling::query()
                    ->where('family_code', $student->family_code)
                    ->delete();

                Student::query()
                    ->where('family_code', $student->family_code)
                    ->delete();
            } else {
                $student->delete();
            }

            if ($matchingStudents->count() <= 2) {
                Student::query()
                    ->whereRaw('LOWER(full_name) = ?', [strtolower($student->full_name)])
                    ->whereRaw('LOWER(COALESCE(class_name, "")) = ?', [strtolower((string) $student->class_name)])
                    ->update(['is_duplicate' => false]);
            }
        });

        return redirect()
            ->route('teacher.records')
            ->with('status', filled($student->family_code)
                ? 'Duplicate family group and its students were removed after review.'
                : 'Duplicate student record removed after review.');
    }
}
