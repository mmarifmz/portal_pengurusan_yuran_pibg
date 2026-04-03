<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentImportController extends Controller
{
    public function create(): View
    {
        return view('students.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulk_rows' => ['required', 'string'],
            'school_code' => ['nullable', 'string', 'max:6'],
            'delimiter' => ['nullable', 'in:comma,pipe'],
        ]);

        $schoolCode = strtoupper(trim($validated['school_code'] ?? 'SSP'));
        $schoolCode = preg_replace('/[^A-Z0-9]/', '', $schoolCode) ?: 'SSP';
        $delimiter = $this->resolveDelimiter($validated['delimiter'] ?? 'comma');

        $lines = preg_split('/\r\n|\r|\n/', trim($validated['bulk_rows']) ?: '');

        $report = [
            'processed' => 0,
            'created' => 0,
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

            if (count($segments) < 3) {
                $report['errors'][] = "Skipped line because it needs kode keluarga, kelas, and nama: {$trimmed}";
                continue;
            }

            [$familyRaw, $className, $fullName] = array_slice($segments, 0, 3);

            $familyCode = $this->normalizeFamilyCode($schoolCode, $familyRaw);
            $className = $this->normalizeClassName($className);
            $fullName = $this->normalizeFullName($fullName);

            $status = 'active';
            $existing = $this->findExistingStudent($familyCode, $fullName);

            if ($existing) {
                $status = "duplicate (family {$familyCode})";
                $report['duplicates'][] = "{$familyCode} / {$fullName}";
            }

            $studentNo = $this->generateStudentNo($schoolCode, $className, $existing?->student_no ?? null);

            Student::create([
                'student_no' => $studentNo,
                'family_code' => $familyCode,
                'class_name' => $className,
                'full_name' => $fullName,
                'status' => $status,
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

            $report['created']++;
        }

        $message = "Processed {$report['processed']} lines.";

        if ($report['created'] > 0) {
            $message .= " Added {$report['created']} students.";
        }

        if ($report['duplicates']) {
            $message .= ' Duplicates: ' . implode(', ', array_slice($report['duplicates'], 0, 5));
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

    private function normalizeFamilyCode(string $schoolCode, string $family): string
    {
        $numeric = preg_replace('/[^0-9]/', '', $family);
        $numeric = $numeric ?: '0';

        return sprintf('%s-%04d', $schoolCode, (int) $numeric);
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

    private function generateStudentNo(string $schoolCode, string $className, ?string $fallback): string
    {
        if ($fallback) {
            return $fallback;
        }

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
}
