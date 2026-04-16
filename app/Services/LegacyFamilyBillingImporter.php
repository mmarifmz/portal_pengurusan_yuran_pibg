<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyFamilyBillingImporter
{
    /**
     * @return array{
     *     processed_rows:int,
     *     matched_rows:int,
     *     unmatched_rows:int,
     *     ambiguous_rows:int,
     *     matched_by_student_code:int,
     *     matched_by_family_code_and_name:int,
     *     matched_by_unique_name_only:int,
     *     billing_rows_upserted:int,
     *     dry_run:bool,
     *     families:array<int, array<string, mixed>>,
     *     unmatched:array<int, array<string, mixed>>,
     *     ambiguous:array<int, array<string, mixed>>
     * }
     */
    public function import(string $path, int $legacyYear, int $currentYear, string $schoolCode = 'SSP', bool $dryRun = false): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Legacy CSV file not found: {$path}");
        }

        $students = Student::query()
            ->where('billing_year', $currentYear)
            ->orderBy('family_code')
            ->orderBy('full_name')
            ->get();

        $lookups = $this->buildLookups($students);
        $rows = $this->readCsv($path);

        $familySummaries = [];
        $report = [
            'processed_rows' => 0,
            'matched_rows' => 0,
            'unmatched_rows' => 0,
            'ambiguous_rows' => 0,
            'matched_by_student_code' => 0,
            'matched_by_family_code_and_name' => 0,
            'matched_by_unique_name_only' => 0,
            'billing_rows_upserted' => 0,
            'dry_run' => $dryRun,
            'families' => [],
            'unmatched' => [],
            'ambiguous' => [],
        ];

        foreach ($rows as $row) {
            $report['processed_rows']++;

            $legacyStudentCode = $this->extractStudentCode($row);
            $legacyFamilyCode = $this->normalizeLegacyFamilyCode(
                (string) ($row['family_id'] ?? $row['family_code'] ?? ''),
                $schoolCode
            );
            $normalizedName = $this->normalizeName((string) ($row['student_name'] ?? $row['full_name'] ?? ''));

            $match = $this->resolveMatch($legacyStudentCode, $legacyFamilyCode, $normalizedName, $lookups);

            if ($match['status'] === 'unmatched') {
                $report['unmatched_rows']++;
                $report['unmatched'][] = [
                    'family_id' => $row['family_id'] ?? $row['family_code'] ?? null,
                    'student_name' => $row['student_name'] ?? $row['full_name'] ?? null,
                    'student_code' => $legacyStudentCode,
                ];
                continue;
            }

            if ($match['status'] === 'ambiguous') {
                $report['ambiguous_rows']++;
                $report['ambiguous'][] = [
                    'family_id' => $row['family_id'] ?? $row['family_code'] ?? null,
                    'student_name' => $row['student_name'] ?? $row['full_name'] ?? null,
                    'student_code' => $legacyStudentCode,
                ];
                continue;
            }

            $report['matched_rows']++;
            $report['matched_by_'.$match['method']]++;

            $resolvedFamilyCode = (string) $match['student']->family_code;
            $paidAmount = (float) ($row['amount_paid'] ?? 0);
            $dueAmount = (float) ($row['amount_due'] ?? 0);
            $reference = trim((string) ($row['payment_reference'] ?? ''));
            $paidAt = trim((string) ($row['paid_at'] ?? ''));
            $paymentStatus = trim((string) ($row['payment_status'] ?? ''));

            if (! isset($familySummaries[$resolvedFamilyCode])) {
                $familySummaries[$resolvedFamilyCode] = [
                    'family_code' => $resolvedFamilyCode,
                    'legacy_family_code' => $legacyFamilyCode,
                    'fee_amount' => $dueAmount,
                    'paid_amount' => $paidAmount,
                    'match_methods' => [$match['label'] => 1],
                    'references' => $reference !== '' ? [$reference => true] : [],
                    'paid_at_values' => $paidAt !== '' ? [$paidAt => true] : [],
                    'payment_statuses' => $paymentStatus !== '' ? [$paymentStatus => true] : [],
                    'matched_students' => [$match['student']->full_name => true],
                ];
            } else {
                $familySummaries[$resolvedFamilyCode]['fee_amount'] = max($familySummaries[$resolvedFamilyCode]['fee_amount'], $dueAmount);
                $familySummaries[$resolvedFamilyCode]['paid_amount'] = max($familySummaries[$resolvedFamilyCode]['paid_amount'], $paidAmount);
                $familySummaries[$resolvedFamilyCode]['match_methods'][$match['label']] = ($familySummaries[$resolvedFamilyCode]['match_methods'][$match['label']] ?? 0) + 1;
                if ($reference !== '') {
                    $familySummaries[$resolvedFamilyCode]['references'][$reference] = true;
                }
                if ($paidAt !== '') {
                    $familySummaries[$resolvedFamilyCode]['paid_at_values'][$paidAt] = true;
                }
                if ($paymentStatus !== '') {
                    $familySummaries[$resolvedFamilyCode]['payment_statuses'][$paymentStatus] = true;
                }
                $familySummaries[$resolvedFamilyCode]['matched_students'][$match['student']->full_name] = true;
            }
        }

        $persist = function () use ($familySummaries, $legacyYear, &$report): void {
            foreach ($familySummaries as $summary) {
                $feeAmount = round((float) $summary['fee_amount'], 2);
                $paidAmount = round((float) $summary['paid_amount'], 2);
                $status = $this->deriveBillingStatus($feeAmount, $paidAmount, array_keys($summary['payment_statuses']));
                $notes = $this->buildNotes($summary);

                FamilyBilling::query()->updateOrCreate(
                    [
                        'family_code' => $summary['family_code'],
                        'billing_year' => $legacyYear,
                    ],
                    [
                        'fee_amount' => $feeAmount,
                        'paid_amount' => $paidAmount,
                        'status' => $status,
                        'notes' => $notes,
                    ]
                );

                $report['billing_rows_upserted']++;
                $report['families'][] = [
                    'family_code' => $summary['family_code'],
                    'legacy_family_code' => $summary['legacy_family_code'],
                    'fee_amount' => $feeAmount,
                    'paid_amount' => $paidAmount,
                    'status' => $status,
                    'match_methods' => implode(', ', array_keys($summary['match_methods'])),
                ];
            }
        };

        if ($dryRun) {
            foreach ($familySummaries as $summary) {
                $report['families'][] = [
                    'family_code' => $summary['family_code'],
                    'legacy_family_code' => $summary['legacy_family_code'],
                    'fee_amount' => round((float) $summary['fee_amount'], 2),
                    'paid_amount' => round((float) $summary['paid_amount'], 2),
                    'status' => $this->deriveBillingStatus(
                        (float) $summary['fee_amount'],
                        (float) $summary['paid_amount'],
                        array_keys($summary['payment_statuses'])
                    ),
                    'match_methods' => implode(', ', array_keys($summary['match_methods'])),
                ];
            }
        } else {
            DB::transaction($persist);
        }

        return $report;
    }

    /**
     * @param Collection<int, Student> $students
     * @return array<string, mixed>
     */
    private function buildLookups(Collection $students): array
    {
        $byStudentCode = [];
        $byFamilyAndName = [];
        $byName = [];

        foreach ($students as $student) {
            foreach ([$student->student_no, $student->ssp_student_id] as $code) {
                $normalizedCode = $this->normalizeStudentCode((string) $code);

                if ($normalizedCode !== '') {
                    $byStudentCode[$normalizedCode] = $student;
                }
            }

            $normalizedName = $this->normalizeName((string) $student->full_name);
            $familyCode = (string) $student->family_code;

            if ($familyCode !== '' && $normalizedName !== '') {
                $byFamilyAndName[$familyCode.'|'.$normalizedName] = $student;
                $byName[$normalizedName][] = $student;
            }
        }

        return [
            'by_student_code' => $byStudentCode,
            'by_family_and_name' => $byFamilyAndName,
            'by_name' => $byName,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file: {$path}");
        }

        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);
            throw new RuntimeException('CSV header row is missing or invalid.');
        }

        $header = array_map(fn ($value) => trim((string) $value), $header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            $row = [];

            foreach ($header as $index => $column) {
                $row[$column] = trim((string) ($data[$index] ?? ''));
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param array<string, string> $row
     */
    private function extractStudentCode(array $row): string
    {
        foreach (['student_code', 'student_no', 'ssp_student_id'] as $column) {
            $value = $this->normalizeStudentCode((string) ($row[$column] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizeLegacyFamilyCode(string $legacyFamilyId, string $schoolCode): string
    {
        $legacyFamilyId = trim($legacyFamilyId);

        if ($legacyFamilyId === '') {
            return '';
        }

        if (preg_match('/^[A-Z]+-\d{4}$/', strtoupper($legacyFamilyId)) === 1) {
            return strtoupper($legacyFamilyId);
        }

        $numeric = preg_replace('/\D+/', '', $legacyFamilyId);

        if ($numeric === null || $numeric === '') {
            return '';
        }

        return sprintf('%s-%04d', strtoupper($schoolCode), (int) $numeric);
    }

    private function normalizeStudentCode(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function normalizeName(string $value): string
    {
        $value = Str::upper(Str::squish($value));

        return preg_replace("/[^A-Z0-9 ]+/", '', $value) ?: '';
    }

    /**
     * @param array<string, mixed> $lookups
     * @return array{status:string, method?:string, label?:string, student?:Student}
     */
    private function resolveMatch(string $studentCode, string $legacyFamilyCode, string $normalizedName, array $lookups): array
    {
        if ($studentCode !== '' && isset($lookups['by_student_code'][$studentCode])) {
            return [
                'status' => 'matched',
                'method' => 'student_code',
                'label' => 'student_code',
                'student' => $lookups['by_student_code'][$studentCode],
            ];
        }

        if ($legacyFamilyCode !== '' && $normalizedName !== '') {
            $familyAndNameKey = $legacyFamilyCode.'|'.$normalizedName;

            if (isset($lookups['by_family_and_name'][$familyAndNameKey])) {
                return [
                    'status' => 'matched',
                    'method' => 'family_code_and_name',
                    'label' => 'family_code_and_name',
                    'student' => $lookups['by_family_and_name'][$familyAndNameKey],
                ];
            }
        }

        if ($normalizedName !== '' && isset($lookups['by_name'][$normalizedName])) {
            $matches = collect($lookups['by_name'][$normalizedName])
                ->unique(fn (Student $student) => $student->family_code)
                ->values();

            if ($matches->count() === 1) {
                return [
                    'status' => 'matched',
                    'method' => 'unique_name_only',
                    'label' => 'unique_name_only',
                    'student' => $matches->first(),
                ];
            }

            return ['status' => 'ambiguous'];
        }

        return ['status' => 'unmatched'];
    }

    /**
     * @param array<int, string> $paymentStatuses
     */
    private function deriveBillingStatus(float $feeAmount, float $paidAmount, array $paymentStatuses): string
    {
        if (in_array('paid', array_map('strtolower', $paymentStatuses), true) || $paidAmount >= $feeAmount) {
            return 'paid';
        }

        if ($paidAmount > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function buildNotes(array $summary): string
    {
        $parts = [
            'Imported from legacy families.csv',
            'Legacy family: '.($summary['legacy_family_code'] ?: '-'),
            'Match methods: '.implode(', ', array_keys($summary['match_methods'])),
            'Matched students: '.implode(', ', array_keys($summary['matched_students'])),
        ];

        if ($summary['references'] !== []) {
            $parts[] = 'References: '.implode(', ', array_keys($summary['references']));
        }

        if ($summary['paid_at_values'] !== []) {
            $parts[] = 'Paid at: '.implode(', ', array_keys($summary['paid_at_values']));
        }

        if ($summary['payment_statuses'] !== []) {
            $parts[] = 'Legacy statuses: '.implode(', ', array_keys($summary['payment_statuses']));
        }

        return implode(' | ', $parts);
    }
}
