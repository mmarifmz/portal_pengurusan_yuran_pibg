<?php

namespace App\Http\Controllers;

use App\Models\LegacyStudentPayment;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class TeacherReconciliationController extends Controller
{
    public function index(): View
    {
        return view('teacher.reconcile', [
            'preview' => null,
            'previewToken' => null,
            'backupFiles' => $this->listBackupFiles(),
        ]);
    }

    public function preview(Request $request): View
    {
        $validated = $request->validate([
            'past_year_csv' => ['required', 'file', 'mimes:csv,txt'],
            'current_year_csv' => ['required', 'file', 'mimes:csv,txt'],
            'school_code' => ['nullable', 'string', 'max:6'],
            'current_year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $schoolCode = strtoupper(trim((string) ($validated['school_code'] ?? 'SSP')));
        $schoolCode = preg_replace('/[^A-Z0-9]/', '', $schoolCode) ?: 'SSP';
        $currentYear = (int) $validated['current_year'];

        $pastRows = $this->parseStudentCsv(
            $request->file('past_year_csv')->getRealPath(),
            $schoolCode
        );
        $currentRows = $this->parseStudentCsv(
            $request->file('current_year_csv')->getRealPath(),
            $schoolCode
        );

        $preview = $this->buildPreview($pastRows, $currentRows, $currentYear);

        $previewToken = 'reconcile:preview:'.Str::uuid();
        Cache::put($previewToken, [
            'school_code' => $schoolCode,
            'current_year' => $currentYear,
            'past_rows' => $pastRows->values()->all(),
            'current_rows' => $currentRows->values()->all(),
            'preview' => $preview,
        ], now()->addMinutes(30));

        return view('teacher.reconcile', [
            'preview' => $preview,
            'previewToken' => $previewToken,
            'backupFiles' => $this->listBackupFiles(),
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preview_token' => ['required', 'string'],
        ]);

        $payload = Cache::get((string) $validated['preview_token']);
        if (! is_array($payload)) {
            return redirect()
                ->route('teacher.reconcile.index')
                ->withErrors(['preview_token' => 'Preview session expired. Please upload CSV again.']);
        }

        $currentRows = collect((array) ($payload['current_rows'] ?? []));
        $leaverRows = collect((array) (data_get($payload, 'preview.leaver_rows') ?? []));
        $currentYear = (int) ($payload['current_year'] ?? now()->year);

        $created = 0;
        $updated = 0;
        $markedLeaver = 0;
        $historicalImported = 0;
        $historicalImportedMatched = 0;
        $historicalImportedUnmatched = 0;
        $historicalSkipped = 0;

        DB::transaction(function () use ($currentRows, $leaverRows, $currentYear, $payload, &$created, &$updated, &$markedLeaver, &$historicalImported, &$historicalImportedMatched, &$historicalImportedUnmatched, &$historicalSkipped): void {
            foreach ($currentRows as $row) {
                $student = $this->findStudentForApply($row);

                $data = [
                    'student_no' => (string) ($row['student_no'] ?? ''),
                    'family_code' => (string) ($row['family_code'] ?? ''),
                    'full_name' => (string) ($row['full_name'] ?? ''),
                    'class_name' => (string) ($row['class_name'] ?? ''),
                    'status' => 'active',
                    'billing_year' => $currentYear,
                    'annual_fee' => 100.00,
                ];

                if ($student) {
                    $student->fill(array_filter($data, fn ($value) => $value !== ''));
                    $student->is_duplicate = false;
                    $student->save();
                    $updated++;
                    continue;
                }

                if ($data['student_no'] === '') {
                    $data['student_no'] = $this->generateStudentNo(
                        (string) data_get($row, 'school_code', 'SSP'),
                        $data['class_name']
                    );
                }

                Student::create([
                    'student_no' => $data['student_no'],
                    'family_code' => $data['family_code'],
                    'ssp_student_id' => $data['student_no'],
                    'full_name' => $data['full_name'],
                    'class_name' => $data['class_name'],
                    'is_duplicate' => false,
                    'parent_name' => null,
                    'parent_phone' => null,
                    'parent_email' => null,
                    'total_fee' => 0,
                    'paid_amount' => 0,
                    'status' => 'active',
                    'billing_year' => $currentYear,
                    'annual_fee' => 100.00,
                    'import_raw_line' => null,
                ]);
                $created++;
            }

            foreach ($leaverRows as $row) {
                $student = $this->findStudentForApply($row);
                if (! $student) {
                    continue;
                }

                $student->fill([
                    'status' => 'leaver',
                ]);

                if (blank($student->class_name) && filled($row['class_name'] ?? '')) {
                    $student->class_name = (string) $row['class_name'];
                }

                $student->save();
                $markedLeaver++;
            }

            [$historicalImported, $historicalImportedMatched, $historicalImportedUnmatched, $historicalSkipped] = $this->importLegacyPaidHistory(
                collect((array) ($payload['past_rows'] ?? [])),
                $currentRows
            );
        });

        Cache::forget((string) $validated['preview_token']);

        return redirect()
            ->route('teacher.reconcile.index')
            ->with('status', "Reconcile applied. Created {$created}, updated {$updated}, marked leaver {$markedLeaver}, historical paid imported {$historicalImported} (matched {$historicalImportedMatched}, unmatched {$historicalImportedUnmatched}), skipped {$historicalSkipped}.");
    }

    public function createBackup(): RedirectResponse
    {
        $connection = config('database.default');
        $db = (array) config("database.connections.{$connection}");

        $database = (string) ($db['database'] ?? '');
        $username = (string) ($db['username'] ?? '');
        $password = (string) ($db['password'] ?? '');
        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (string) ($db['port'] ?? '3306');

        if ($database === '' || $username === '') {
            return redirect()
                ->route('teacher.reconcile.index')
                ->withErrors(['backup' => 'Database credentials are incomplete for backup.']);
        }

        $fileName = sprintf('pibg-backup-%s.sql.gz', now()->format('Ymd-His'));
        $relativePath = 'backups/'.$fileName;

        $process = new Process([
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            $database,
        ], null, $password !== '' ? ['MYSQL_PWD' => $password] : null);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            return redirect()
                ->route('teacher.reconcile.index')
                ->withErrors(['backup' => 'Backup failed: '.trim($process->getErrorOutput())]);
        }

        $sql = $process->getOutput();
        if (trim($sql) === '') {
            return redirect()
                ->route('teacher.reconcile.index')
                ->withErrors(['backup' => 'Backup failed: mysqldump returned empty output.']);
        }

        Storage::disk('local')->put($relativePath, gzencode($sql, 9) ?: $sql);

        return redirect()
            ->route('teacher.reconcile.index')
            ->with('status', "Backup created: {$fileName}");
    }

    public function downloadBackup(Request $request, string $fileName): Response
    {
        abort_unless($this->isValidBackupFileName($fileName), 404);

        $path = 'backups/'.$fileName;
        abort_unless(Storage::disk('local')->exists($path), 404);

        if ($request->string('format')->lower()->toString() === 'sql') {
            $content = Storage::disk('local')->get($path);
            $sql = str_ends_with($fileName, '.gz') ? gzdecode($content) : $content;
            abort_if($sql === false, 500, 'Unable to decode backup file.');

            $downloadName = str_ends_with($fileName, '.gz')
                ? substr($fileName, 0, -3)
                : $fileName;

            return response((string) $sql, 200, [
                'Content-Type' => 'application/sql; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$downloadName.'"',
            ]);
        }

        return Storage::disk('local')->download($path, $fileName, [
            'Content-Type' => 'application/gzip',
        ]);
    }

    public function deleteBackup(string $fileName): RedirectResponse
    {
        abort_unless($this->isValidBackupFileName($fileName), 404);

        $path = 'backups/'.$fileName;
        abort_unless(Storage::disk('local')->exists($path), 404);

        $deleted = Storage::disk('local')->delete($path);
        if (! $deleted) {
            return redirect()
                ->route('teacher.reconcile.index')
                ->withErrors(['backup' => 'Delete failed. Please try again.']);
        }

        return redirect()
            ->route('teacher.reconcile.index')
            ->with('status', "Backup deleted: {$fileName}");
    }

    private function parseStudentCsv(string $path, string $schoolCode): Collection
    {
        $rows = collect();

        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');

        $headerMap = null;
        foreach ($file as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
            $row = array_values($row);

            if ($this->isBlankCsvRow($row)) {
                continue;
            }

            if ($index === 0 && $this->looksLikeHeader($row)) {
                $headerMap = $this->buildHeaderMap($row);
                continue;
            }

            $normalized = $this->normalizeCsvStudentRow($row, $schoolCode, $headerMap);
            if (! $normalized) {
                continue;
            }

            $rows->push($normalized);
        }

        return $rows
            ->unique(fn (array $row) => $this->buildMatchKey($row))
            ->values();
    }

    private function normalizeCsvStudentRow(array $row, string $schoolCode, ?array $headerMap): ?array
    {
        $familyRaw = $this->pickValue($row, $headerMap, ['family_code', 'family_id', 'family']);
        $nameRaw = $this->pickValue($row, $headerMap, ['student_name', 'full_name', 'name', 'student']);
        $classRaw = $this->pickValue($row, $headerMap, ['class_name', 'class', 'kelas']);
        $studentNoRaw = $this->pickValue($row, $headerMap, ['student_no', 'ssp_student_id']);
        $paymentStatusRaw = $this->pickValue($row, $headerMap, ['payment_status', 'status']);
        $amountDueRaw = $this->pickValue($row, $headerMap, ['amount_due', 'fee_amount', 'annual_fee']);
        $amountPaidRaw = $this->pickValue($row, $headerMap, ['amount_paid', 'paid_amount']);
        $paymentReferenceRaw = $this->pickValue($row, $headerMap, ['payment_reference', 'reference', 'transaction_reference', 'transaction_id', 'ref_no']);
        $paidAtRaw = $this->pickValue($row, $headerMap, ['paid_at', 'payment_date', 'payment_datetime']);

        if ($headerMap === null && count($row) >= 3) {
            if ($studentNoRaw === '' && count($row) >= 4 && $this->looksLikeStudentNo((string) $row[0])) {
                $studentNoRaw = (string) $row[0];
                $familyRaw = $familyRaw !== '' ? $familyRaw : (string) ($row[1] ?? '');
                $classRaw = $classRaw !== '' ? $classRaw : (string) ($row[2] ?? '');
                $nameRaw = $nameRaw !== '' ? $nameRaw : (string) ($row[3] ?? '');
            } else {
                $familyRaw = $familyRaw !== '' ? $familyRaw : (string) ($row[0] ?? '');
                $classRaw = $classRaw !== '' ? $classRaw : (string) ($row[1] ?? '');
                $nameRaw = $nameRaw !== '' ? $nameRaw : (string) ($row[2] ?? '');
            }
        }

        $fullName = $this->normalizeName($nameRaw);
        $className = $this->normalizeClass($classRaw);
        $familyCode = $this->normalizeFamilyCode($familyRaw, $schoolCode);
        $studentNo = strtoupper(trim($studentNoRaw));

        if ($fullName === '' || $familyCode === '') {
            return null;
        }

        return [
            'student_no' => $studentNo,
            'family_code' => $familyCode,
            'full_name' => $fullName,
            'class_name' => $className,
            'class_year' => $this->extractClassYear($className),
            'school_code' => $schoolCode,
            'payment_status' => strtolower(trim($paymentStatusRaw)),
            'amount_due' => $this->normalizeAmount($amountDueRaw),
            'amount_paid' => $this->normalizeAmount($amountPaidRaw),
            'payment_reference' => trim((string) $paymentReferenceRaw),
            'paid_at' => $this->normalizeDateTimeString($paidAtRaw),
            'source_year' => $this->inferSourceYear(trim((string) $paidAtRaw)),
        ];
    }

    private function buildPreview(Collection $pastRows, Collection $currentRows, int $currentYear): array
    {
        $leaverRows = $pastRows
            ->filter(function (array $pastRow) use ($currentRows): bool {
                $isClassSix = ((int) ($pastRow['class_year'] ?? 0)) === 6;
                if (! $isClassSix) {
                    return false;
                }

                return $this->findMatchingCurrentRow($pastRow, $currentRows) === null;
            })
            ->values();

        $paidHistoryRows = $pastRows
            ->filter(fn (array $row): bool => $this->isPaidHistoryRow($row))
            ->values();

        $importablePaidHistoryRows = $paidHistoryRows
            ->filter(fn (array $row): bool => $this->findMatchingCurrentRow($row, $currentRows) !== null)
            ->values();

        $unmatchedPaidHistoryRows = $paidHistoryRows
            ->filter(fn (array $row): bool => $this->findMatchingCurrentRow($row, $currentRows) === null)
            ->values();

        $existingStudents = Student::query()->get();
        $existingByStudentNo = $existingStudents
            ->filter(fn (Student $student): bool => filled($student->student_no))
            ->keyBy(fn (Student $student) => strtoupper((string) $student->student_no));

        $existingByNameFamily = $existingStudents
            ->keyBy(fn (Student $student) => $this->normalizeFamilyCode((string) $student->family_code, 'SSP').'|'.$this->normalizeName((string) $student->full_name));

        $toCreate = 0;
        $toUpdate = 0;

        foreach ($currentRows as $row) {
            $studentNo = strtoupper((string) ($row['student_no'] ?? ''));
            $familyNameKey = $this->normalizeFamilyCode((string) ($row['family_code'] ?? ''), 'SSP').'|'.$this->normalizeName((string) ($row['full_name'] ?? ''));

            if (($studentNo !== '' && $existingByStudentNo->has($studentNo)) || $existingByNameFamily->has($familyNameKey)) {
                $toUpdate++;
            } else {
                $toCreate++;
            }
        }

        return [
            'current_year' => $currentYear,
            'past_rows_count' => $pastRows->count(),
            'current_rows_count' => $currentRows->count(),
            'to_create_count' => $toCreate,
            'to_update_count' => $toUpdate,
            'leaver_count' => $leaverRows->count(),
            'leaver_rows' => $leaverRows->take(200)->values()->all(),
            'paid_history_rows_count' => $paidHistoryRows->count(),
            'importable_paid_history_count' => $importablePaidHistoryRows->count(),
            'unmatched_paid_history_count' => $unmatchedPaidHistoryRows->count(),
            'paid_history_amount_total' => (float) $importablePaidHistoryRows->sum(fn (array $row): float => (float) ($row['amount_paid'] ?? 0)),
            'paid_history_donation_total' => (float) $importablePaidHistoryRows->sum(function (array $row): float {
                $paid = (float) ($row['amount_paid'] ?? 0);
                $due = (float) ($row['amount_due'] ?? 0);
                return max(0, $paid - $due);
            }),
            'unmatched_paid_history_rows' => $unmatchedPaidHistoryRows
                ->take(100)
                ->values()
                ->all(),
        ];
    }

    private function buildMatchKey(array $row): string
    {
        $studentNo = strtoupper(trim((string) ($row['student_no'] ?? '')));
        if ($studentNo !== '') {
            return 'student_no:'.$studentNo;
        }

        return 'family_name:'.($row['family_code'] ?? '').'|'.($row['full_name'] ?? '');
    }

    private function buildFamilyNameKey(string $familyCode, string $fullName): string
    {
        return strtoupper(trim($familyCode)).'|'.$this->normalizeName($fullName);
    }

    private function findStudentForApply(array $row): ?Student
    {
        $studentNo = strtoupper(trim((string) ($row['student_no'] ?? '')));
        if ($studentNo !== '') {
            $student = Student::query()
                ->whereRaw('UPPER(student_no) = ?', [$studentNo])
                ->first();
            if ($student) {
                return $student;
            }
        }

        $familyCode = (string) ($row['family_code'] ?? '');
        $fullName = (string) ($row['full_name'] ?? '');

        if ($familyCode === '' || $fullName === '') {
            return null;
        }

        return Student::query()
            ->where('family_code', $familyCode)
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($fullName)])
            ->first();
    }

    private function findMatchingCurrentRow(array $pastRow, Collection $currentRows): ?array
    {
        $pastStudentNo = strtoupper(trim((string) ($pastRow['student_no'] ?? '')));

        if ($pastStudentNo !== '') {
            $matchByStudentNo = $currentRows->first(function (array $row) use ($pastStudentNo): bool {
                return strtoupper(trim((string) ($row['student_no'] ?? ''))) === $pastStudentNo;
            });
            if (is_array($matchByStudentNo)) {
                return $matchByStudentNo;
            }
        }

        $pastSoftName = $this->normalizeNameForSoftMatch((string) ($pastRow['full_name'] ?? ''));
        if ($pastSoftName === '') {
            return null;
        }

        $nameMatches = $currentRows
            ->filter(function (array $row) use ($pastSoftName): bool {
                return $this->normalizeNameForSoftMatch((string) ($row['full_name'] ?? '')) === $pastSoftName;
            })
            ->values();

        if ($nameMatches->isEmpty()) {
            return null;
        }

        if ($nameMatches->count() === 1) {
            $single = $nameMatches->first();
            return is_array($single) ? $single : null;
        }

        $pastClassYear = (int) ($pastRow['class_year'] ?? 0);
        $preferredYear = $pastClassYear > 0 ? $pastClassYear + 1 : 0;

        if ($preferredYear > 0) {
            $progressed = $nameMatches->first(function (array $row) use ($preferredYear): bool {
                return (int) ($row['class_year'] ?? 0) === $preferredYear;
            });

            if (is_array($progressed)) {
                return $progressed;
            }
        }

        if ($pastClassYear > 0) {
            $sameYear = $nameMatches->first(function (array $row) use ($pastClassYear): bool {
                return (int) ($row['class_year'] ?? 0) === $pastClassYear;
            });

            if (is_array($sameYear)) {
                return $sameYear;
            }
        }

        $familyNameKey = $this->buildFamilyNameKey(
            (string) ($pastRow['family_code'] ?? ''),
            (string) ($pastRow['full_name'] ?? '')
        );
        $exactFamilyName = $nameMatches->first(function (array $row) use ($familyNameKey): bool {
            return $this->buildFamilyNameKey(
                (string) ($row['family_code'] ?? ''),
                (string) ($row['full_name'] ?? '')
            ) === $familyNameKey;
        });

        if (is_array($exactFamilyName)) {
            return $exactFamilyName;
        }

        $first = $nameMatches->first();
        return is_array($first) ? $first : null;
    }

    private function importLegacyPaidHistory(Collection $pastRows, Collection $currentRows): array
    {
        $currentFamilyCodes = $currentRows
            ->pluck('family_code')
            ->filter()
            ->unique()
            ->values();

        $students = Student::query()
            ->whereIn('family_code', $currentFamilyCodes)
            ->get();

        $studentsByStudentNo = $students
            ->filter(fn (Student $student): bool => filled($student->student_no))
            ->keyBy(fn (Student $student) => strtoupper((string) $student->student_no));

        $studentsByFamilyName = $students
            ->keyBy(fn (Student $student) => $this->buildFamilyNameKey(
                (string) $student->family_code,
                (string) $student->full_name
            ));

        $imported = 0;
        $importedMatched = 0;
        $importedUnmatched = 0;
        $skipped = 0;

        foreach ($pastRows as $pastRow) {
            if (! $this->isPaidHistoryRow($pastRow)) {
                continue;
            }

            $matchedCurrentRow = $this->findMatchingCurrentRow($pastRow, $currentRows);
            $student = null;
            $targetStudentNo = strtoupper(trim((string) ($pastRow['student_no'] ?? '')));
            $targetFamilyCode = strtoupper(trim((string) ($pastRow['family_code'] ?? '')));
            $targetName = $this->normalizeName((string) ($pastRow['full_name'] ?? ''));
            $targetClass = (string) ($pastRow['class_name'] ?? '');
            $sourceYear = (int) ($pastRow['source_year'] ?? $this->inferSourceYear((string) ($pastRow['paid_at'] ?? '')));

            if (is_array($matchedCurrentRow)) {
                $currentStudentNo = strtoupper(trim((string) ($matchedCurrentRow['student_no'] ?? '')));
                $currentFamilyCode = strtoupper(trim((string) ($matchedCurrentRow['family_code'] ?? '')));
                $currentName = (string) ($matchedCurrentRow['full_name'] ?? '');
                $currentClass = (string) ($matchedCurrentRow['class_name'] ?? '');

                if ($currentStudentNo !== '' && $studentsByStudentNo->has($currentStudentNo)) {
                    $student = $studentsByStudentNo->get($currentStudentNo);
                }

                if (! $student) {
                    $familyNameKey = $this->buildFamilyNameKey($currentFamilyCode, $currentName);
                    $student = $studentsByFamilyName->get($familyNameKey);
                }

                if ($student) {
                    $targetStudentNo = (string) ($student->student_no ?? $currentStudentNo);
                    $targetFamilyCode = $currentFamilyCode;
                    $targetName = $this->normalizeName($currentName);
                    $targetClass = $currentClass;
                }
            }

            if (! $student) {
                $targetFamilyCode = $this->toLegacyFamilyBucketCode($targetFamilyCode, $sourceYear);
            }

            if ($targetFamilyCode === '' || $targetName === '') {
                $skipped++;
                continue;
            }

            $amountPaid = (float) ($pastRow['amount_paid'] ?? 0);
            $amountDue = (float) ($pastRow['amount_due'] ?? 0);
            $donation = max(0, $amountPaid - $amountDue);

            $paymentReference = trim((string) ($pastRow['payment_reference'] ?? ''));
            if ($paymentReference === '') {
                // Keep historical row unique even when gateway reference is missing.
                $paymentReference = 'NOREF-'.substr(md5(json_encode($pastRow)), 0, 12);
            }

            LegacyStudentPayment::query()->updateOrCreate(
                [
                    'source_year' => $sourceYear,
                    'family_code' => $targetFamilyCode,
                    'student_name' => $targetName,
                    'payment_reference' => $paymentReference,
                ],
                [
                    'student_id' => $student?->id,
                    'student_no' => $targetStudentNo !== '' ? $targetStudentNo : null,
                    'class_name' => $targetClass !== '' ? $targetClass : null,
                    'payment_status' => 'paid',
                    'amount_due' => $amountDue,
                    'amount_paid' => $amountPaid,
                    'donation_amount' => $donation,
                    'paid_at' => (string) ($pastRow['paid_at'] ?? '') !== '' ? $pastRow['paid_at'] : null,
                    'raw_payload' => $pastRow,
                ]
            );

            $imported++;
            if ($student) {
                $importedMatched++;
            } else {
                $importedUnmatched++;
            }
        }

        return [$imported, $importedMatched, $importedUnmatched, $skipped];
    }

    private function listBackupFiles(): Collection
    {
        return collect(Storage::disk('local')->files('backups'))
            ->filter(function (string $path): bool {
                $name = basename($path);
                return $this->isValidBackupFileName($name);
            })
            ->map(function (string $path): array {
                return [
                    'name' => basename($path),
                    'size' => Storage::disk('local')->size($path),
                    'last_modified' => Storage::disk('local')->lastModified($path),
                ];
            })
            ->sortByDesc('last_modified')
            ->values();
    }

    private function isValidBackupFileName(string $fileName): bool
    {
        return preg_match('/^pibg-backup-\d{8}-\d{6}\.sql(\.gz)?$/', $fileName) === 1;
    }

    private function toLegacyFamilyBucketCode(string $familyCode, int $sourceYear): string
    {
        $clean = strtoupper(trim($familyCode));
        $clean = preg_replace('/[^A-Z0-9-]+/', '-', $clean) ?? '';
        $clean = trim($clean, '-');

        if ($clean === '') {
            $clean = 'UNKNOWN';
        }

        return sprintf('LEGACY-%d-%s', $sourceYear, $clean);
    }

    private function generateStudentNo(string $schoolCode, string $className): string
    {
        $yearDigit = $this->extractClassYear($className);
        $yearDigit = $yearDigit > 0 ? $yearDigit : 0;

        $try = 0;
        do {
            $random = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = sprintf('%s%d%s', $schoolCode, $yearDigit, $random);
            $exists = Student::query()->where('student_no', $candidate)->exists();
            $try++;
        } while ($exists && $try < 10);

        return $candidate;
    }

    private function normalizeFamilyCode(string $rawFamilyCode, string $schoolCode): string
    {
        $raw = strtoupper(trim($rawFamilyCode));
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^[A-Z]{2,5}-\d+$/', $raw) === 1) {
            [$prefix, $num] = explode('-', $raw, 2);
            return sprintf('%s-%04d', $prefix, (int) $num);
        }

        if (preg_match('/^F(\d+)$/', $raw, $matches) === 1) {
            return sprintf('%s-%04d', $schoolCode, (int) $matches[1]);
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits !== '') {
            return sprintf('%s-%04d', $schoolCode, (int) $digits);
        }

        return $raw;
    }

    private function normalizeName(string $name): string
    {
        $value = mb_strtoupper(trim($name));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim((string) $value);
    }

    private function normalizeNameForSoftMatch(string $name): string
    {
        $value = $this->normalizeName($name);
        $value = preg_replace('/[^A-Z0-9 ]+/', '', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim((string) $value);
    }

    private function normalizeClass(string $class): string
    {
        $value = mb_strtoupper(trim($class));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim((string) $value);
    }

    private function extractClassYear(string $class): int
    {
        if (preg_match('/^(\d+)/', trim($class), $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function isBlankCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function looksLikeHeader(array $row): bool
    {
        $normalized = collect($row)
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->values();

        return $normalized->contains(fn ($value) => in_array($value, [
            'family_id', 'family_code', 'student_name', 'full_name', 'class_name', 'payment_status',
        ], true));
    }

    private function buildHeaderMap(array $row): array
    {
        $map = [];
        foreach ($row as $index => $header) {
            $normalized = strtolower(trim((string) $header));
            $normalized = str_replace([' ', '-'], '_', $normalized);
            $map[$normalized] = $index;
        }

        return $map;
    }

    private function pickValue(array $row, ?array $headerMap, array $candidates): string
    {
        if (is_array($headerMap)) {
            foreach ($candidates as $candidate) {
                if (! array_key_exists($candidate, $headerMap)) {
                    continue;
                }

                $index = (int) $headerMap[$candidate];
                return trim((string) ($row[$index] ?? ''));
            }
        }

        return '';
    }

    private function looksLikeStudentNo(string $value): bool
    {
        $raw = strtoupper(trim($value));

        return preg_match('/^[A-Z]{2,6}\d{4,}$/', $raw) === 1;
    }

    private function normalizeAmount(string $value): float
    {
        $clean = str_replace([',', 'RM', 'rm'], '', trim($value));
        if ($clean === '') {
            return 0.0;
        }

        return round((float) $clean, 2);
    }

    private function normalizeDateTimeString(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            return (string) Carbon::parse($trimmed)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function inferSourceYear(string $paidAt): int
    {
        $parsed = $this->normalizeDateTimeString($paidAt);
        if ($parsed) {
            return (int) substr($parsed, 0, 4);
        }

        return (int) now()->year;
    }

    private function isPaidHistoryRow(array $row): bool
    {
        $status = strtolower(trim((string) ($row['payment_status'] ?? '')));
        $amountPaid = (float) ($row['amount_paid'] ?? 0);

        return $status === 'paid' && $amountPaid > 0;
    }
}
