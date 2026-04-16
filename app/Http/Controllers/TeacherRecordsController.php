<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class TeacherRecordsController extends Controller
{
    public function index(Request $request): View
    {
        $billingYear = now()->year;
        $recordFilter = (string) $request->string('record_filter')->toString();
        $selectedClass = trim((string) $request->string('class_name')->toString());
        $familyCodeQuery = trim((string) $request->string('family_code')->toString());
        $studentNameQuery = trim((string) $request->string('student_name')->toString());
        $studentNameSearchQuery = mb_strlen($studentNameQuery) >= 3 ? $studentNameQuery : '';
        $studentNameTooShort = $studentNameQuery !== '' && mb_strlen($studentNameQuery) < 3;

        $students = Student::query()
            ->orderBy('family_code')
            ->orderBy('full_name')
            ->get();

        $onboardedParentUserIds = ParentLoginOtp::query()
            ->whereNotNull('user_id')
            ->whereNotNull('used_at')
            ->distinct()
            ->pluck('user_id');

        $onboardedParentUsers = User::query()
            ->where('role', 'parent')
            ->whereIn('id', $onboardedParentUserIds)
            ->get(['email', 'phone']);

        $onboardedParentEmails = $onboardedParentUsers
            ->pluck('email')
            ->filter()
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique();

        $onboardedParentPhones = $onboardedParentUsers
            ->pluck('phone')
            ->filter()
            ->map(fn ($phone) => $this->normalizePhoneForMatch((string) $phone))
            ->filter()
            ->unique();

        $availableClasses = Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->values();

        $filteredStudents = $students
            ->when($recordFilter === 'duplicates', fn ($collection) => $collection->filter(fn (Student $student) => $student->is_duplicate))
            ->when($recordFilter === 'registered-parent', function ($collection) use ($onboardedParentEmails, $onboardedParentPhones) {
                return $collection->filter(function (Student $student) use ($onboardedParentEmails, $onboardedParentPhones): bool {
                    $studentEmail = mb_strtolower(trim((string) ($student->parent_email ?? '')));
                    $studentPhone = $this->normalizePhoneForMatch((string) ($student->parent_phone ?? ''));

                    if ($studentEmail !== '' && $onboardedParentEmails->contains($studentEmail)) {
                        return true;
                    }

                    if ($studentPhone !== '' && $onboardedParentPhones->contains($studentPhone)) {
                        return true;
                    }

                    return false;
                });
            })
            ->when($selectedClass !== '', fn ($collection) => $collection->filter(fn (Student $student) => (string) $student->class_name === $selectedClass))
            ->when($familyCodeQuery !== '', function ($collection) use ($familyCodeQuery) {
                $needle = mb_strtolower($familyCodeQuery);

                return $collection->filter(function (Student $student) use ($needle): bool {
                    return str_contains(mb_strtolower((string) ($student->family_code ?? '')), $needle);
                });
            })
            ->when($studentNameSearchQuery !== '', function ($collection) use ($studentNameSearchQuery) {
                $needle = mb_strtolower($studentNameSearchQuery);

                return $collection->filter(function (Student $student) use ($needle): bool {
                    return str_contains(mb_strtolower((string) ($student->full_name ?? '')), $needle);
                });
            })
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

        $filtersActive = $recordFilter !== ''
            || $selectedClass !== ''
            || $familyCodeQuery !== ''
            || $studentNameQuery !== '';
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
            'availableClasses' => $availableClasses,
            'recordFilter' => $recordFilter,
            'selectedClass' => $selectedClass,
            'familyCodeQuery' => $familyCodeQuery,
            'studentNameQuery' => $studentNameQuery,
            'studentNameTooShort' => $studentNameTooShort,
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

    public function familyDetail(string $familyCode): View
    {
        $students = Student::query()
            ->where('family_code', $familyCode)
            ->orderBy('full_name')
            ->get();

        abort_if($students->isEmpty(), 404);

        $familyBillings = FamilyBilling::query()
            ->where('family_code', $familyCode)
            ->orderByDesc('billing_year')
            ->get();

        $paymentFilter = $this->normalizePaymentFilter((string) request()->query('payment_status', 'all'));

        $paymentHistory = FamilyPaymentTransaction::query()
            ->with(['familyBilling', 'user'])
            ->whereIn('family_billing_id', $familyBillings->pluck('id'))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (FamilyPaymentTransaction $payment): bool => $this->paymentMatchesFilter($payment, $paymentFilter))
            ->values();

        $parentRawPhones = $students
            ->pluck('parent_phone')
            ->filter()
            ->unique();

        $parentPhoneVariants = $parentRawPhones
            ->flatMap(fn ($phone) => $this->buildPhoneMatchVariants((string) $phone))
            ->filter()
            ->unique()
            ->values();

        $parentNormalizedPhones = $parentRawPhones
            ->map(fn ($phone) => $this->normalizePhoneForMatch((string) $phone))
            ->filter()
            ->unique()
            ->values();

        $parentEmails = $students
            ->pluck('parent_email')
            ->filter()
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter()
            ->unique();

        $linkedParents = User::query()
            ->where('role', 'parent')
            ->orderBy('name')
            ->get()
            ->filter(function (User $user) use ($parentEmails, $parentNormalizedPhones, $parentPhoneVariants): bool {
                $userEmail = mb_strtolower(trim((string) ($user->email ?? '')));
                $userPhone = trim((string) ($user->phone ?? ''));
                $userNormalizedPhone = $this->normalizePhoneForMatch($userPhone);

                return ($userEmail !== '' && $parentEmails->contains($userEmail))
                    || ($userPhone !== '' && $parentPhoneVariants->contains($userPhone))
                    || ($userNormalizedPhone !== '' && $parentNormalizedPhones->contains($userNormalizedPhone));
            })
            ->values();

        $accessLogsQuery = ParentLoginOtp::query();
        $linkedParentIds = $linkedParents->pluck('id')->filter()->values();

        if ($linkedParentIds->isNotEmpty() || $parentPhoneVariants->isNotEmpty()) {
            $accessLogsQuery->where(function ($query) use ($linkedParentIds, $parentPhoneVariants) {
                if ($linkedParentIds->isNotEmpty()) {
                    $query->orWhereIn('user_id', $linkedParentIds->all());
                }

                if ($parentPhoneVariants->isNotEmpty()) {
                    $query->orWhereIn('phone', $parentPhoneVariants->all());
                }
            });
        } else {
            $accessLogsQuery->whereRaw('1 = 0');
        }

        $accessLogs = $accessLogsQuery
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $successfulLogins = $accessLogs->filter(fn (ParentLoginOtp $log): bool => $log->used_at !== null)->count();
        $latestAccessAt = $accessLogs->first()?->created_at;
        $isOnboarded = $linkedParents->isNotEmpty() && $successfulLogins > 0;

        $currentBilling = $familyBillings->first();
        $totalPaid = (float) $familyBillings->sum('paid_amount');
        $totalBilled = (float) $familyBillings->sum('fee_amount');
        $totalOutstanding = max(0, $totalBilled - $totalPaid);
        $legacyPayments = LegacyStudentPayment::query()
            ->where('family_code', $familyCode)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();
        $legacyPaidTotal = (float) $legacyPayments->sum('amount_paid');
        $legacyDonationTotal = (float) $legacyPayments->sum('donation_amount');

        return view('teacher.family-profile', [
            'familyCode' => $familyCode,
            'students' => $students,
            'linkedParents' => $linkedParents,
            'familyBillings' => $familyBillings,
            'paymentHistory' => $paymentHistory,
            'accessLogs' => $accessLogs,
            'currentBilling' => $currentBilling,
            'totalPaid' => $totalPaid,
            'totalBilled' => $totalBilled,
            'totalOutstanding' => $totalOutstanding,
            'paymentFilter' => $paymentFilter,
            'isOnboarded' => $isOnboarded,
            'successfulLogins' => $successfulLogins,
            'latestAccessAt' => $latestAccessAt,
            'legacyPayments' => $legacyPayments,
            'legacyPaidTotal' => $legacyPaidTotal,
            'legacyDonationTotal' => $legacyDonationTotal,
        ]);
    }

    public function exportFamilyPayments(Request $request, string $familyCode): StreamedResponse
    {
        $students = Student::query()
            ->where('family_code', $familyCode)
            ->get();

        abort_if($students->isEmpty(), 404);

        $familyBillings = FamilyBilling::query()
            ->where('family_code', $familyCode)
            ->orderByDesc('billing_year')
            ->get();

        $paymentFilter = $this->normalizePaymentFilter((string) $request->query('payment_status', 'all'));

        $payments = FamilyPaymentTransaction::query()
            ->with(['familyBilling', 'user'])
            ->whereIn('family_billing_id', $familyBillings->pluck('id'))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (FamilyPaymentTransaction $payment): bool => $this->paymentMatchesFilter($payment, $paymentFilter))
            ->values();

        $filename = sprintf(
            'family-payments-%s-%s.csv',
            strtolower($familyCode),
            now()->format('Ymd-His')
        );

        return response()->streamDownload(function () use ($payments): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Date',
                'Order ID',
                'Bill Code',
                'Amount (RM)',
                'Status',
                'Return Status',
                'Payer Name',
                'Payer Email',
                'Payer Phone',
            ]);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    optional($payment->paid_at ?? $payment->created_at)->format('Y-m-d H:i:s'),
                    (string) $payment->external_order_id,
                    (string) ($payment->provider_bill_code ?? ''),
                    number_format((float) $payment->amount, 2, '.', ''),
                    (string) $payment->status,
                    (string) ($payment->return_status ?? ''),
                    (string) ($payment->payer_name ?? ''),
                    (string) ($payment->payer_email ?? ''),
                    (string) ($payment->payer_phone ?? ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function normalizePhoneForMatch(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '60')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '6'.$digits;
        }

        if (str_starts_with($digits, '1')) {
            return '60'.$digits;
        }

        return $digits;
    }

    private function buildPhoneMatchVariants(string $phone): Collection
    {
        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $normalized = $this->normalizePhoneForMatch($raw);

        $variants = collect([$raw, $digits, $normalized])
            ->filter()
            ->values();

        if ($digits !== '' && str_starts_with($digits, '60')) {
            $withoutCountry = substr($digits, 2);
            if ($withoutCountry !== '') {
                $variants->push($withoutCountry);
                $variants->push('0'.$withoutCountry);
            }
        }

        if ($digits !== '' && str_starts_with($digits, '0')) {
            $variants->push('6'.$digits);
            $variants->push('60'.substr($digits, 1));
        }

        return $variants
            ->filter()
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
    }

    private function normalizePaymentFilter(string $filter): string
    {
        $normalized = strtolower(trim($filter));

        return in_array($normalized, ['all', 'successful', 'pending', 'cancelled'], true)
            ? $normalized
            : 'all';
    }

    private function paymentMatchesFilter(FamilyPaymentTransaction $payment, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }

        $status = strtolower(trim((string) $payment->status));
        $returnStatus = strtolower(trim((string) ($payment->return_status ?? '')));

        if ($filter === 'successful') {
            return in_array($status, ['success', 'successful', 'paid'], true)
                || $returnStatus === 'successful';
        }

        if ($filter === 'pending') {
            return in_array($status, ['pending', 'processing'], true)
                || in_array($returnStatus, ['pending completion', 'pending'], true);
        }

        return in_array($status, ['failed', 'cancelled', 'canceled', 'superseded'], true)
            || in_array($returnStatus, ['parent cancel', 'not enough fund', 'not successful', 'cancelled'], true);
    }
}
