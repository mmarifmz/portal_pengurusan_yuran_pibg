<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\ParentLoginOtp;
use App\Models\ParentLoginAudit;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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

        $paidThisYearFamilyCodes = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->where(function ($query): void {
                $query->where('status', 'paid')
                    ->orWhereColumn('paid_amount', '>=', 'fee_amount');
            })
            ->pluck('family_code')
            ->filter()
            ->map(fn ($familyCode) => (string) $familyCode)
            ->unique()
            ->values();

        $lastYear = $billingYear - 1;
        $paidLastYearFamilyCodes = FamilyBilling::query()
            ->where('billing_year', $lastYear)
            ->where(function ($query): void {
                $query->where('status', 'paid')
                    ->orWhereColumn('paid_amount', '>=', 'fee_amount');
            })
            ->pluck('family_code')
            ->filter()
            ->map(fn ($familyCode) => (string) $familyCode)
            ->merge(
                LegacyStudentPayment::query()
                    ->where('source_year', $lastYear)
                    ->where('payment_status', 'paid')
                    ->pluck('family_code')
                    ->filter()
                    ->map(fn ($familyCode) => (string) $familyCode)
            )
            ->unique()
            ->values();

        $filteredStudents = $students
            ->when($recordFilter === 'duplicates', fn ($collection) => $collection->filter(fn (Student $student) => $student->is_duplicate))
            ->when($recordFilter === 'paid-this-year', fn ($collection) => $collection->filter(
                fn (Student $student) => filled($student->family_code) && $paidThisYearFamilyCodes->contains((string) $student->family_code)
            ))
            ->when($recordFilter === 'paid-last-year', fn ($collection) => $collection->filter(
                fn (Student $student) => filled($student->family_code) && $paidLastYearFamilyCodes->contains((string) $student->family_code)
            ))
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

        $studentParentEmails = $filteredStudents
            ->pluck('parent_email')
            ->filter()
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        $studentParentPhones = $filteredStudents
            ->pluck('parent_phone')
            ->filter()
            ->values();

        $studentParentPhoneVariants = $studentParentPhones
            ->flatMap(fn ($phone) => $this->buildPhoneMatchVariants((string) $phone))
            ->filter()
            ->unique()
            ->values();

        $linkedParentUsers = ($studentParentEmails->isEmpty() && $studentParentPhoneVariants->isEmpty())
            ? collect()
            : User::query()
                ->where('role', 'parent')
                ->where(function ($query) use ($studentParentEmails, $studentParentPhoneVariants): void {
                    if ($studentParentEmails->isNotEmpty()) {
                        $query->orWhereIn('email', $studentParentEmails->all());
                    }

                    if ($studentParentPhoneVariants->isNotEmpty()) {
                        $query->orWhereIn('phone', $studentParentPhoneVariants->all());
                    }
                })
                ->orderBy('name')
                ->get(['name', 'email', 'phone']);

        $parentNameByEmail = collect();
        $parentNameByPhone = collect();

        foreach ($linkedParentUsers as $parentUser) {
            $parentName = trim((string) ($parentUser->name ?? ''));

            if ($parentName === '') {
                continue;
            }

            $emailKey = mb_strtolower(trim((string) ($parentUser->email ?? '')));
            if ($emailKey !== '' && ! $parentNameByEmail->has($emailKey)) {
                $parentNameByEmail->put($emailKey, $parentName);
            }

            $phoneKey = $this->normalizePhoneForMatch((string) ($parentUser->phone ?? ''));
            if ($phoneKey !== '' && ! $parentNameByPhone->has($phoneKey)) {
                $parentNameByPhone->put($phoneKey, $parentName);
            }
        }

        $studentsWithResolvedParents = $filteredStudents
            ->map(function (Student $student) use ($parentNameByEmail, $parentNameByPhone): Student {
                $studentEmail = mb_strtolower(trim((string) ($student->parent_email ?? '')));
                $studentPhone = $this->normalizePhoneForMatch((string) ($student->parent_phone ?? ''));

                $resolvedParentName = null;
                $studentParentName = trim((string) ($student->parent_name ?? ''));
                $studentParentNameIsPlaceholder = preg_match('/^parent\s+ssp-/i', $studentParentName) === 1;

                if ($studentParentName !== '' && ! $studentParentNameIsPlaceholder) {
                    $resolvedParentName = $studentParentName;
                } elseif ($studentEmail !== '' && $parentNameByEmail->has($studentEmail)) {
                    $resolvedParentName = $parentNameByEmail->get($studentEmail);
                } elseif ($studentPhone !== '' && $parentNameByPhone->has($studentPhone)) {
                    $resolvedParentName = $parentNameByPhone->get($studentPhone);
                }

                $student->setAttribute('resolved_parent_name', $resolvedParentName ?: $student->parent_name);

                return $student;
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
            'lastYear' => $lastYear,
            'students' => $studentsWithResolvedParents,
            'paidThisYearFamilyCodes' => $paidThisYearFamilyCodes,
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
            'paidLastYearFamilyCodes' => $paidLastYearFamilyCodes,
            'socialTagLabels' => $this->enabledSocialTagLabels(),
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
            ->with(['phones' => fn ($query) => $query->orderBy('id')])
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
        $portalDonationByPaymentId = $paymentHistory
            ->mapWithKeys(fn (FamilyPaymentTransaction $payment): array => [
                $payment->id => $this->isPaymentSuccessful($payment)
                    ? $this->resolvePortalDonationAmount($payment)
                    : 0.0,
            ]);

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

        $attachedFamilyPhones = $familyBillings
            ->flatMap(fn (FamilyBilling $billing) => $billing->phones->pluck('phone'))
            ->map(fn ($phone) => trim((string) $phone))
            ->filter()
            ->unique()
            ->values();

        $attachedFamilyNormalizedPhones = $attachedFamilyPhones
            ->map(fn (string $phone) => $this->normalizePhoneForMatch($phone))
            ->filter()
            ->unique()
            ->values();

        $latestFamilyPhoneLoginByNormalized = $attachedFamilyNormalizedPhones->isEmpty()
            ? collect()
            : ParentLoginAudit::query()
                ->selectRaw('normalized_phone, MAX(logged_in_at) as latest_logged_in_at')
                ->whereIn('normalized_phone', $attachedFamilyNormalizedPhones->all())
                ->groupBy('normalized_phone')
                ->get()
                ->keyBy('normalized_phone');

        $familyPhoneAccess = $attachedFamilyPhones
            ->map(function (string $phone) use ($latestFamilyPhoneLoginByNormalized): array {
                $normalized = $this->normalizePhoneForMatch($phone);
                $latestLogin = $normalized !== ''
                    ? $latestFamilyPhoneLoginByNormalized->get($normalized)?->latest_logged_in_at
                    : null;

                return [
                    'phone' => $phone,
                    'latest_login_at' => $latestLogin ? now()->parse((string) $latestLogin) : null,
                ];
            })
            ->values();

        $currentBilling = $familyBillings->first();
        $totalPaid = (float) $familyBillings->sum('paid_amount');
        $totalBilled = (float) $familyBillings->sum('fee_amount');
        $totalOutstanding = max(0, $totalBilled - $totalPaid);
        $parentProfileName = (string) ($students->pluck('parent_name')->filter()->first() ?? '');
        $parentProfileEmail = (string) ($students->pluck('parent_email')->filter()->first() ?? '');
        $studentIds = $students->pluck('id')->filter()->values();
        $studentNames = $students
            ->pluck('full_name')
            ->map(fn ($name) => $this->normalizeNameForLegacyMatch((string) $name))
            ->filter()
            ->unique();

        $legacyPaymentCandidates = LegacyStudentPayment::query()
            ->where(function ($query) use ($familyCode, $studentIds) {
                $query->where('family_code', $familyCode);

                if ($studentIds->isNotEmpty()) {
                    $query->orWhereIn('student_id', $studentIds->all());
                }
            })
            ->where('payment_status', 'paid')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $legacyPayments = $legacyPaymentCandidates
            ->filter(function (LegacyStudentPayment $payment) use ($familyCode, $studentIds, $studentNames): bool {
                if ($payment->student_id !== null && $studentIds->contains((int) $payment->student_id)) {
                    return true;
                }

                if ((string) $payment->family_code !== $familyCode) {
                    return false;
                }

                $legacyName = $this->normalizeNameForLegacyMatch((string) $payment->student_name);
                return $legacyName !== '' && $studentNames->contains($legacyName);
            })
            ->values();

        $legacyPayments = $legacyPayments
            ->groupBy(function (LegacyStudentPayment $payment): string {
                $reference = trim((string) $payment->payment_reference);
                if ($reference !== '') {
                    return $reference;
                }

                return sprintf(
                    'NOREF:%s:%s:%0.2f:%d',
                    (string) $payment->family_code,
                    optional($payment->paid_at)->format('Y-m-d H:i:s') ?? '-',
                    (float) $payment->amount_paid,
                    (int) $payment->id
                );
            })
            ->map(function (Collection $group): object {
                /** @var LegacyStudentPayment $first */
                $first = $group->first();

                return (object) [
                    'paid_at' => $group->pluck('paid_at')->filter()->sort()->first() ?? $first->paid_at,
                    'payment_reference' => $first->payment_reference,
                    'amount_paid' => (float) $group->max('amount_paid'),
                    'donation_amount' => (float) $group->max('donation_amount'),
                    'source_year' => (int) ($first->source_year ?? now()->year),
                ];
            })
            ->sortByDesc(fn (object $row) => $row->paid_at ? $row->paid_at->timestamp : 0)
            ->values();

        $legacyPaidTotal = (float) $legacyPayments->sum(fn (object $row): float => (float) $row->amount_paid);
        $legacyDonationTotal = (float) $legacyPayments->sum(fn (object $row): float => (float) $row->donation_amount);

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
            'parentProfileName' => $parentProfileName,
            'parentProfileEmail' => $parentProfileEmail,
            'paymentFilter' => $paymentFilter,
            'isOnboarded' => $isOnboarded,
            'successfulLogins' => $successfulLogins,
            'latestAccessAt' => $latestAccessAt,
            'familyPhoneAccess' => $familyPhoneAccess,
            'portalDonationByPaymentId' => $portalDonationByPaymentId,
            'legacyPayments' => $legacyPayments,
            'legacyPaidTotal' => $legacyPaidTotal,
            'legacyDonationTotal' => $legacyDonationTotal,
            'socialTagLabels' => $this->enabledSocialTagLabels(),
        ]);
    }

    public function updateFamilyParentProfile(Request $request, string $familyCode): RedirectResponse
    {
        $students = Student::query()
            ->where('family_code', $familyCode)
            ->get();

        abort_if($students->isEmpty(), 404);

        $validated = $request->validate([
            'parent_name' => ['nullable', 'string', 'max:255', 'required_without:parent_email'],
            'parent_email' => ['nullable', 'email', 'max:255', 'required_without:parent_name'],
        ]);

        $parentName = array_key_exists('parent_name', $validated)
            ? trim((string) $validated['parent_name'])
            : null;
        $parentEmail = array_key_exists('parent_email', $validated)
            ? mb_strtolower(trim((string) $validated['parent_email']))
            : null;

        $updates = [];
        if ($parentName !== null && $parentName !== '') {
            $updates['parent_name'] = $parentName;
        }
        if ($parentEmail !== null && $parentEmail !== '') {
            $updates['parent_email'] = $parentEmail;
        }

        if ($updates === []) {
            return redirect()
                ->route('teacher.records.family', ['familyCode' => $familyCode])
                ->withErrors(['parent_name' => 'Sila isi sekurang-kurangnya nama atau email untuk dikemas kini.']);
        }

        Student::query()
            ->where('family_code', $familyCode)
            ->update($updates);

        return redirect()
            ->route('teacher.records.family', ['familyCode' => $familyCode])
            ->with('status', 'Family parent profile updated successfully.');
    }

    public function updateStudentTags(Request $request, Student $student): RedirectResponse|JsonResponse
    {
        $enabledTagFields = collect(array_keys($this->enabledSocialTagLabels()));

        if ($enabledTagFields->isEmpty()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'No social tags configured. Please set social tags in System Admin settings first.',
                ], 422);
            }

            return redirect()
                ->route('teacher.records.family', ['familyCode' => (string) $student->family_code])
                ->withErrors(['tags' => 'No social tags configured. Please set social tags in System Admin settings first.']);
        }

        $validated = $request->validate(
            $enabledTagFields
                ->mapWithKeys(fn (string $field): array => [$field => ['nullable', 'boolean']])
                ->all()
        );

        $updates = $enabledTagFields
            ->mapWithKeys(fn (string $field): array => [$field => array_key_exists($field, $validated)])
            ->all();

        $student->update($updates);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Student social tags updated successfully.',
                'student_id' => $student->id,
                'updated_tags' => $updates,
            ]);
        }

        return redirect()
            ->route('teacher.records.family', ['familyCode' => (string) $student->family_code])
            ->with('status', 'Student social tags updated successfully.');
    }

    public function exportFamilyPayments(Request $request, string $familyCode): StreamedResponse
    {
        $students = Student::query()
            ->where('family_code', $familyCode)
            ->get();

        abort_if($students->isEmpty(), 404);

        $familyBillings = FamilyBilling::query()
            ->where('family_code', $familyCode)
            ->with(['phones' => fn ($query) => $query->orderBy('id')])
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
                'Sumbangan (RM)',
                'Status',
                'Return Status',
                'Payer Name',
                'Payer Email',
                'Payer Phone',
            ]);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    optional($payment->paid_at ?? $payment->created_at)->format('Y-m-d H:i:s'),
                    (string) $payment->external_order_display,
                    (string) ($payment->provider_bill_code ?? ''),
                    number_format((float) $payment->amount, 2, '.', ''),
                    number_format($this->isPaymentSuccessful($payment) ? $this->resolvePortalDonationAmount($payment) : 0, 2, '.', ''),
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



    /**
     * @return array<string, string>
     */
    private function configuredSocialTagLabels(): array
    {
        $settings = SiteSetting::getMany([
            'social_tag_label_b40' => 'B40',
            'social_tag_label_kwap' => 'KWAP',
            'social_tag_label_rmt' => 'RMT',
        ]);

        return [
            'is_b40' => trim((string) ($settings['social_tag_label_b40'] ?? '')),
            'is_kwap' => trim((string) ($settings['social_tag_label_kwap'] ?? '')),
            'is_rmt' => trim((string) ($settings['social_tag_label_rmt'] ?? '')),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function enabledSocialTagLabels(): array
    {
        return collect($this->configuredSocialTagLabels())
            ->filter(fn (string $label): bool => $label !== '')
            ->map(fn (string $label): string => mb_strtoupper($label))
            ->all();
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

    private function isPaymentSuccessful(FamilyPaymentTransaction $payment): bool
    {
        $status = strtolower(trim((string) $payment->status));
        $returnStatus = strtolower(trim((string) ($payment->return_status ?? '')));

        return in_array($status, ['success', 'successful', 'paid'], true)
            || $returnStatus === 'successful';
    }

    private function resolvePortalDonationAmount(FamilyPaymentTransaction $payment): float
    {
        $storedDonation = (float) ($payment->donation_amount ?? 0);
        if ($storedDonation > 0) {
            return round($storedDonation, 2);
        }

        return round(max(0, (float) $payment->amount - (float) ($payment->fee_amount_paid ?? 0)), 2);
    }

    private function normalizeNameForLegacyMatch(string $name): string
    {
        $value = mb_strtoupper(trim($name));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim((string) $value);
    }
}
