<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Support\ParentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicParentSearchController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Collection<int, array<string, mixed>> $familyResults */
        $familyResults = collect();
        $searchBillings = collect();
        $hasSearched = false;
        $totalFamilyResults = 0;
        $visibleLimit = max(20, min((int) $request->integer('visible_limit', 20), 200));

        if ($request->filled('student_keyword') || $request->filled('class_name') || $request->filled('contact')) {
            $validated = $request->validate([
                'student_keyword' => ['nullable', 'string', 'max:100', 'required_without_all:class_name,contact'],
                'class_name' => ['nullable', 'string', 'max:50', 'required_without_all:student_keyword,contact'],
                'contact' => ['nullable', 'string', 'max:30', 'required_without_all:student_keyword,class_name'],
            ]);

            $hasSearched = true;

            /** @var Collection<int, Student> $students */
            $contactInput = trim((string) ($validated['contact'] ?? ''));
            $normalizedContact = ParentPhone::normalizeForMatch($contactInput);
            $contactVariants = $contactInput !== '' ? ParentPhone::variants($contactInput) : [];

            $students = Student::query()
                ->when($validated['student_keyword'] ?? null, function ($query, $keyword) {
                    $query->where(function ($nested) use ($keyword) {
                        $nested->where('full_name', 'like', "%{$keyword}%")
                            ->orWhere('student_no', 'like', "%{$keyword}%")
                            ->orWhere('class_name', 'like', "%{$keyword}%");
                    });
                })
                ->when($validated['class_name'] ?? null, function ($query, $className) {
                    $query->where('class_name', $className);
                })
                ->when($contactInput !== '', function ($query) use ($contactVariants, $normalizedContact): void {
                    $query->where(function ($nested) use ($contactVariants, $normalizedContact): void {
                        if ($contactVariants !== []) {
                            $nested->whereIn('parent_phone', $contactVariants);
                        }

                        if ($normalizedContact !== '') {
                            $nested->orWhereIn('family_code', FamilyBilling::query()
                                ->whereHas('phones', fn ($phoneQuery) => $phoneQuery->where('normalized_phone', $normalizedContact))
                                ->select('family_code'));
                        }
                    });
                })
                ->orderByRaw('CASE WHEN family_code IS NULL OR family_code = "" THEN 1 ELSE 0 END')
                ->orderBy('family_code')
                ->orderBy('full_name')
                ->take(300)
                ->get();

            $searchBillings = FamilyBilling::query()
                ->whereIn('family_code', $students->pluck('family_code')->filter()->unique())
                ->orderByDesc('billing_year')
                ->orderByDesc('id')
                ->get()
                ->unique('family_code')
                ->keyBy('family_code');

            $familyResults = $students
                ->groupBy(fn (Student $student) => $student->family_code ?: 'NO_FAMILY::'.$student->id)
                ->map(function (Collection $group, string $groupKey) use ($searchBillings): array {
                    /** @var Student $primaryStudent */
                    $primaryStudent = $group->first();
                    $familyCode = $groupKey !== '' && ! str_starts_with($groupKey, 'NO_FAMILY::') ? $groupKey : null;
                    $billing = $familyCode ? ($searchBillings[$familyCode] ?? null) : null;

                    return [
                        'group_key' => $groupKey,
                        'family_code' => $familyCode,
                        'billing' => $billing,
                        'parent_phone' => $group->pluck('parent_phone')->filter()->first(),
                        'masked_parent_phone' => $this->maskParentPhonePrefix(
                            $group->pluck('parent_phone')->filter()->first()
                        ),
                        'has_registered_phone' => $group->pluck('parent_phone')->filter()->isNotEmpty(),
                        'student_count' => $group->count(),
                        'classes' => $group->pluck('class_name')->filter()->unique()->values(),
                        'students' => $group
                            ->sortBy('full_name')
                            ->values()
                            ->map(function (Student $student): array {
                                return [
                                    'student_no' => $student->student_no,
                                    'full_name' => $student->full_name,
                                    'masked_name' => $this->maskStudentName($student->full_name),
                                    'class_name' => $student->class_name,
                                ];
                            }),
                        'sort_name' => strtolower((string) $primaryStudent->full_name),
                    ];
                })
                ->sortBy([
                    ['family_code', 'asc'],
                    ['sort_name', 'asc'],
                ])
                ->values();

            $totalFamilyResults = $familyResults->count();
            $familyResults = $familyResults->take($visibleLimit)->values();
        }

        $availableClasses = Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->values();

        return view('parent.search', [
            'familyResults' => $familyResults,
            'searchBillings' => $searchBillings,
            'hasSearched' => $hasSearched,
            'totalFamilyResults' => $totalFamilyResults,
            'visibleLimit' => $visibleLimit,
            'availableClasses' => $availableClasses,
        ]);
    }

    public function selectFamily(Request $request, FamilyBilling $familyBilling): RedirectResponse
    {
        $parent = $request->user();

        abort_unless($parent?->isParent(), 403, 'Unauthorized role access.');

        $parentPhone = ParentPhone::sanitizeInput((string) $parent->phone);

        if ($parentPhone === '') {
            return redirect()->route('parent.search')
                ->withErrors(['contact' => 'Nombor telefon akaun parent tidak sah. Sila hubungi admin sekolah.']);
        }

        $existingPhones = $this->resolveExistingFamilyPhones($familyBilling);
        $familyAlreadyLinked = $existingPhones->isNotEmpty();
        $primaryPhone = $existingPhones->first();

        if (! $familyBilling->hasRegisteredPhone($parentPhone) && ! $familyBilling->registerPhone($parentPhone)) {
            return redirect()->route('parent.search')->withErrors([
                'contact' => 'Keluarga ini sudah mempunyai 5 nombor telefon berdaftar. Sila hubungi admin sekolah.',
            ]);
        }

        $request->session()->put('parent_child_selection_completed', true);
        $request->session()->put('parent_selected_family_billing_id', $familyBilling->id);

        if ($familyAlreadyLinked) {
            $notice = 'Keluarga ini sudah mempunyai nombor penjaga berdaftar. Akses diteruskan ke keluarga yang dipilih.';

            if (filled($primaryPhone)) {
                $notice .= ' Notifikasi keselamatan dihantar ke nombor utama '.$this->maskPrimaryPhoneForNotice((string) $primaryPhone).'.';
            }

            return redirect()->route('parent.payments.checkout', $familyBilling)
                ->with('status', $notice);
        }

        return redirect()->route('parent.dashboard')
            ->with('status', 'Keluarga berjaya dipautkan. Anda kini boleh semak dashboard ibu bapa.');
    }

    private function maskStudentName(string $fullName): string
    {
        $trimmed = trim($fullName);

        if ($trimmed === '') {
            return '-';
        }

        $length = mb_strlen($trimmed);
        $visibleLength = max(3, (int) ceil($length * 0.6));
        $maskedLength = max(1, $length - $visibleLength);

        return mb_substr($trimmed, 0, $visibleLength).str_repeat('#', $maskedLength);
    }

    private function maskParentPhonePrefix(?string $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 4) {
            return str_repeat('#', strlen($digits));
        }

        return substr($digits, 0, max(0, strlen($digits) - 4)).'####';
    }

    /**
     * @return Collection<int, string>
     */
    private function resolveExistingFamilyPhones(FamilyBilling $familyBilling): Collection
    {
        $registeredPhones = $familyBilling->phones()
            ->orderBy('id')
            ->pluck('phone')
            ->map(fn ($phone) => ParentPhone::sanitizeInput((string) $phone));

        $studentPhones = Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->whereNotNull('parent_phone')
            ->where('parent_phone', '!=', '')
            ->orderBy('id')
            ->pluck('parent_phone')
            ->map(fn ($phone) => ParentPhone::sanitizeInput((string) $phone));

        return $registeredPhones
            ->merge($studentPhones)
            ->filter()
            ->unique()
            ->values();
    }

    private function maskPrimaryPhoneForNotice(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return 'XXXX';
        }

        if (str_starts_with($digits, '60')) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '1')) {
            $digits = '0'.$digits;
        }

        $prefixLength = max(2, min(6, strlen($digits) - 4));
        $prefix = substr($digits, 0, $prefixLength);

        return '+'.$prefix.'XXXX';
    }
}
