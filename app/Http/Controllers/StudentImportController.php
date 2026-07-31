<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentImportController extends Controller
{
    private const SCHOOL_CODE = 'SSP';

    public function create(): View
    {
        return view('students.import', [
            'nextFamilyCode' => $this->formatFamilyCode($this->nextFamilySequence()),
            'existingFamilies' => $this->existingFamilyOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulk_rows' => ['required', 'string'],
            'delimiter' => ['nullable', 'in:comma,pipe'],
            'import_mode' => ['required', 'in:new,existing'],
            'existing_family_code' => ['nullable', 'required_if:import_mode,existing', 'string', 'max:255'],
        ]);

        $delimiter = $this->resolveDelimiter($validated['delimiter'] ?? 'comma');
        $importMode = $validated['import_mode'];
        $existingFamilyCode = $importMode === 'existing'
            ? strtoupper(trim((string) ($validated['existing_family_code'] ?? '')))
            : null;

        if ($existingFamilyCode !== null && ! $this->familyExists($existingFamilyCode)) {
            throw ValidationException::withMessages([
                'existing_family_code' => 'Select an existing family code from the list.',
            ]);
        }

        $nextFamilySequence = $this->nextFamilySequence();

        $lines = preg_split('/\r\n|\r|\n/', trim($validated['bulk_rows']) ?: '');

        $report = [
            'processed' => 0,
            'created' => 0,
            'first_family_code' => null,
            'last_family_code' => null,
            'duplicates' => [],
            'errors' => [],
        ];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $report['processed']++;
            $segments = $this->splitRow($trimmed, $delimiter);

            if (count($segments) < 2) {
                $report['errors'][] = "Skipped line because it needs nama murid and kelas: {$trimmed}";

                continue;
            }

            [$fullName, $className] = array_slice($segments, 0, 2);

            $familyCode = $existingFamilyCode ?? $this->formatFamilyCode($nextFamilySequence);
            $className = $this->normalizeClassName($className);
            $fullName = $this->normalizeFullName($fullName);
            $isDuplicate = $this->hasDuplicateNameAndClass($fullName, $className);

            $existing = $this->findExistingStudent($familyCode, $fullName);

            if ($existing) {
                $report['duplicates'][] = "{$familyCode} / {$fullName}";

                continue;
            }

            $studentNo = $this->generateStudentNo(self::SCHOOL_CODE, $className);

            Student::create([
                'student_no' => $studentNo,
                'family_code' => $familyCode,
                'class_name' => $className,
                'full_name' => $fullName,
                'is_duplicate' => $isDuplicate,
                'status' => 'active',
                'total_fee' => 0,
                'paid_amount' => 0,
                'parent_name' => null,
                'parent_phone' => null,
                'parent_email' => null,
                'billing_year' => (int) date('Y'),
                'annual_fee' => 100.00,
                'ssp_student_id' => $studentNo,
                'import_raw_line' => $trimmed,
            ]);

            if ($isDuplicate) {
                $this->markDuplicates($fullName, $className);
            }

            $report['created']++;
            $report['first_family_code'] ??= $familyCode;
            $report['last_family_code'] = $familyCode;
            if ($importMode === 'new') {
                $nextFamilySequence++;
            }
        }

        $message = "Processed {$report['processed']} lines.";

        if ($report['created'] > 0) {
            $message .= " Added {$report['created']} students.";
            $message .= $importMode === 'existing'
                ? " Linked them to existing family {$report['first_family_code']}."
                : ($report['first_family_code'] === $report['last_family_code']
                ? " Assigned family code {$report['first_family_code']}."
                : " Assigned family codes {$report['first_family_code']} to {$report['last_family_code']}.");
        }

        if ($report['duplicates']) {
            $message .= ' Duplicates: '.implode(', ', array_slice($report['duplicates'], 0, 5));
        }

        if ($report['errors']) {
            $message .= ' Errors detected.';
        }

        return redirect()
            ->route('students.import.form')
            ->with('student_import_message', $message);
    }

    private function splitRow(string $line, string $delimiter): array
    {
        $segments = str_getcsv($line, $delimiter);

        return array_values(array_filter(array_map('trim', $segments), fn ($value) => $value !== ''));
    }

    private function nextFamilySequence(): int
    {
        $pattern = '/^'.preg_quote(self::SCHOOL_CODE, '/').'-(\d+)$/';
        $codes = Student::query()
            ->where('family_code', 'like', self::SCHOOL_CODE.'-%')
            ->pluck('family_code')
            ->merge(
                FamilyBilling::query()
                    ->where('family_code', 'like', self::SCHOOL_CODE.'-%')
                    ->pluck('family_code')
            );

        $highestSequence = $codes
            ->map(function ($familyCode) use ($pattern): ?int {
                return preg_match($pattern, trim((string) $familyCode), $matches)
                    ? (int) $matches[1]
                    : null;
            })
            ->filter(fn (?int $sequence): bool => $sequence !== null)
            ->max();

        return ((int) $highestSequence) + 1;
    }

    private function formatFamilyCode(int $sequence): string
    {
        return sprintf('%s-%04d', self::SCHOOL_CODE, $sequence);
    }

    /**
     * @return Collection<int, array{family_code: string, students: string}>
     */
    private function existingFamilyOptions(): Collection
    {
        return Student::query()
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->orderBy('family_code')
            ->orderBy('full_name')
            ->get(['family_code', 'full_name', 'class_name'])
            ->groupBy(fn (Student $student): string => trim((string) $student->family_code))
            ->map(function (Collection $students, string $familyCode): array {
                $studentSummary = $students
                    ->map(fn (Student $student): string => trim($student->full_name.' · '.($student->class_name ?: 'No class')))
                    ->implode('; ');

                return [
                    'family_code' => $familyCode,
                    'students' => $studentSummary,
                ];
            })
            ->values();
    }

    private function familyExists(string $familyCode): bool
    {
        return Student::query()
            ->where('family_code', $familyCode)
            ->exists();
    }

    private function normalizeClassName(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function normalizeFullName(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function findExistingStudent(string $familyCode, string $fullName): ?Student
    {
        return Student::where('family_code', $familyCode)
            ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
            ->first();
    }

    private function resolveDelimiter(string $key): string
    {
        return match ($key) {
            'pipe' => '|',
            default => ',',
        };
    }

    private function generateStudentNo(string $schoolCode, string $className): string
    {
        $yearDigit = (int) filter_var($className, FILTER_SANITIZE_NUMBER_INT);
        $yearDigit = $yearDigit > 0 ? $yearDigit : 0;
        $try = 0;

        do {
            $random = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = sprintf('%s%d%s', $schoolCode, $yearDigit, $random);
            $exists = Student::where('student_no', $candidate)->exists();
            $try++;
        } while ($exists && $try < 5);

        if ($exists) {
            $candidate = sprintf('%s%d%sX', $schoolCode, $yearDigit, $random);
        }

        return $candidate;
    }

    private function hasDuplicateNameAndClass(string $fullName, string $className): bool
    {
        return Student::query()
            ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
            ->whereRaw('LOWER(COALESCE(class_name, "")) = ?', [strtolower($className)])
            ->exists();
    }

    private function markDuplicates(string $fullName, string $className): void
    {
        Student::query()
            ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
            ->whereRaw('LOWER(COALESCE(class_name, "")) = ?', [strtolower($className)])
            ->update(['is_duplicate' => true]);
    }
}
