<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TeacherUserImportAudit;
use App\Models\User;
use App\Support\MalaysianPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SplFileObject;

class TeacherUserImportService
{
    private const REQUIRED_COLUMNS = ['Name', 'Phone', 'Email', 'Group', 'Class'];

    public function __construct(private readonly TeacherUserInviteService $teacherUserInviteService)
    {
    }

    /**
     * @param  array{assign_class?:bool,enable_whatsapp?:bool,send_invite?:bool}  $options
     * @return array{
     *   filename:string,
     *   total_rows:int,
     *   created:int,
     *   updated:int,
     *   assigned_to_class:int,
     *   no_class_matched:int,
     *   class_assignment_skipped:int,
     *   duplicate_emails_updated:int,
     *   whatsapp_enabled:int,
     *   invite_sent:int,
     *   invite_manual:int,
     *   failed_rows_count:int,
     *   failed_rows:array<int, array<string, mixed>>,
     *   warnings:array<int, string>,
     *   manual_invites:array<int, array{name:string,phone:string,message:string}>
     * }
     */
    public function import(string $path, array $options = [], ?User $importedBy = null, ?string $filename = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('The uploaded CSV file could not be read.');
        }

        $options = [
            'assign_class' => (bool) ($options['assign_class'] ?? false),
            'enable_whatsapp' => (bool) ($options['enable_whatsapp'] ?? false),
            'send_invite' => (bool) ($options['send_invite'] ?? false),
        ];

        $summary = [
            'filename' => $filename ?: basename($path),
            'total_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'assigned_to_class' => 0,
            'no_class_matched' => 0,
            'class_assignment_skipped' => 0,
            'duplicate_emails_updated' => 0,
            'whatsapp_enabled' => 0,
            'invite_sent' => 0,
            'invite_manual' => 0,
            'failed_rows_count' => 0,
            'failed_rows' => [],
            'warnings' => [],
            'manual_invites' => [],
        ];

        $classMap = $this->buildClassMap();
        $classOwners = $this->buildClassOwnerMap();
        $csv = new SplFileObject($path);
        $csv->setFlags(SplFileObject::READ_CSV);

        $headerMap = null;
        $rowNumber = 0;

        foreach ($csv as $row) {
            if (! is_array($row) || $this->isBlankRow($row)) {
                continue;
            }

            $rowNumber++;

            if ($headerMap === null) {
                $headerMap = $this->buildHeaderMap($row);

                continue;
            }

            $summary['total_rows']++;

            $record = $this->extractRecord($row, $headerMap);
            $result = $this->importRow($record, $rowNumber, $options, $classMap, $classOwners);

            foreach ($result['warnings'] as $warning) {
                $summary['warnings'][] = $warning;
            }

            if ($result['failed']) {
                $summary['failed_rows_count']++;
                $summary['failed_rows'][] = [
                    'row_number' => $rowNumber,
                    'name' => $record['name'],
                    'phone' => $record['phone'],
                    'email' => $record['email'],
                    'group' => $record['group'],
                    'class' => $record['class'],
                    'error' => $result['error'],
                ];

                continue;
            }

            $summary['created'] += $result['created'] ? 1 : 0;
            $summary['updated'] += $result['updated'] ? 1 : 0;
            $summary['assigned_to_class'] += $result['assigned_to_class'] ? 1 : 0;
            $summary['no_class_matched'] += $result['no_class_matched'] ? 1 : 0;
            $summary['class_assignment_skipped'] += $result['class_assignment_skipped'] ? 1 : 0;
            $summary['duplicate_emails_updated'] += $result['duplicate_email_updated'] ? 1 : 0;
            $summary['whatsapp_enabled'] += $result['whatsapp_enabled'] ? 1 : 0;
            $summary['invite_sent'] += $result['invite_status'] === 'sent' ? 1 : 0;
            $summary['invite_manual'] += $result['invite_status'] === 'manual' ? 1 : 0;

            if ($result['manual_invite'] !== null) {
                $summary['manual_invites'][] = $result['manual_invite'];
            }
        }

        if ($headerMap === null) {
            throw new InvalidArgumentException('The CSV file is empty.');
        }

        if (Schema::hasTable('teacher_user_import_audits')) {
            TeacherUserImportAudit::create([
                'imported_by' => $importedBy?->id,
                'filename' => $summary['filename'],
                'total_rows' => $summary['total_rows'],
                'success_count' => $summary['created'] + $summary['updated'],
                'failed_count' => $summary['failed_rows_count'],
                'options' => $options,
            ]);
        } else {
            $summary['warnings'][] = 'Import audit table is missing, so this import was not recorded in audit history.';
        }

        return $summary;
    }

    /**
     * @param  array<string, int>  $headerMap
     * @return array{name:string,phone:string,email:string,group:string,class:string}
     */
    private function extractRecord(array $row, array $headerMap): array
    {
        return [
            'name' => $this->cell($row, $headerMap['name']),
            'phone' => $this->cell($row, $headerMap['phone']),
            'email' => $this->cell($row, $headerMap['email']),
            'group' => $this->cell($row, $headerMap['group']),
            'class' => $this->cell($row, $headerMap['class']),
        ];
    }

    /**
     * @param  array{name:string,phone:string,email:string,group:string,class:string}  $record
     * @param  array{assign_class:bool,enable_whatsapp:bool,send_invite:bool}  $options
     * @param  array<string, string>  $classMap
     * @param  array<string, int>  $classOwners
     * @return array{
     *   failed:bool,
     *   error:?string,
     *   created:bool,
     *   updated:bool,
     *   assigned_to_class:bool,
     *   no_class_matched:bool,
     *   class_assignment_skipped:bool,
     *   duplicate_email_updated:bool,
     *   whatsapp_enabled:bool,
     *   invite_status:?string,
     *   manual_invite:?array{name:string,phone:string,message:string},
     *   warnings:array<int, string>
     * }
     */
    private function importRow(array $record, int $rowNumber, array $options, array $classMap, array &$classOwners): array
    {
        $warnings = [];
        $name = trim(preg_replace('/\s+/', ' ', $record['name']) ?? '');
        $email = mb_strtolower(trim($record['email']));
        $group = trim(preg_replace('/\s+/', ' ', $record['group']) ?? '');
        $classInput = trim(preg_replace('/\s+/', ' ', $record['class']) ?? '');
        $phone = MalaysianPhone::normalize($record['phone']);

        if ($name === '' || $email === '' || $group === '' || $classInput === '') {
            return $this->failedRow('Missing one or more required values in Name, Phone, Email, Group, or Class.');
        }

        if ($phone === null) {
            return $this->failedRow('Invalid Malaysian WhatsApp number.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failedRow('Invalid email address.');
        }

        $existing = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existing !== null && ! $existing->isTeacher()) {
            return $this->failedRow('This email already belongs to a non-teacher account.');
        }

        $phoneConflict = $this->findPhoneConflict($phone, $existing?->id);
        if ($phoneConflict !== null) {
            return $this->failedRow('This WhatsApp number already belongs to another user.');
        }

        $matchedClassName = null;
        $noClassMatched = false;
        $classAssignmentSkipped = false;

        if ($options['assign_class']) {
            $classKey = $this->normalizeClassKey($classInput);
            $matchedClassName = $classMap[$classKey] ?? null;

            if ($matchedClassName === null) {
                $noClassMatched = true;
                $warnings[] = "Row {$rowNumber}: class [{$classInput}] was not found, so the teacher was imported without a new class assignment.";
            } else {
                $ownerId = $classOwners[$classKey] ?? null;

                if ($ownerId !== null && $ownerId !== $existing?->id) {
                    $matchedClassName = null;
                    $classAssignmentSkipped = true;
                    $warnings[] = "Row {$rowNumber}: class [{$classInput}] already has another teacher assigned, so the class assignment was skipped.";
                }
            }
        }

        $wasCreated = false;
        $wasUpdated = false;
        $assignedToClass = false;
        $whatsappEnabled = false;
        $inviteStatus = null;
        $manualInvite = null;

        DB::transaction(function () use (
            &$existing,
            $name,
            $email,
            $phone,
            $matchedClassName,
            $options,
            &$wasCreated,
            &$wasUpdated,
            &$assignedToClass,
            &$whatsappEnabled,
            &$inviteStatus,
            &$manualInvite,
            &$classOwners
        ): void {
            $user = $existing ?? new User();

            if ($existing === null) {
                $user->password = Str::password(16);
                $user->invite_status = 'pending';
                $wasCreated = true;
            } else {
                $wasUpdated = true;
            }

            $user->name = $name;
            $user->email = $email;
            $user->phone = $phone;
            $user->role = 'teacher';
            $user->is_active = true;

            if ($matchedClassName !== null) {
                $user->class_name = $matchedClassName;
                $assignedToClass = true;
            }

            $user->save();

            $existing = $user;

            if ($matchedClassName !== null) {
                $classOwners[$this->normalizeClassKey($matchedClassName)] = $user->id;
            }

            if ($options['enable_whatsapp']
                && $user->is_active
                && filled($user->phone)
                && filled($user->class_name)
                && ! $user->receive_whatsapp_notifications) {
                $user->forceFill([
                    'receive_whatsapp_notifications' => true,
                ])->save();

                $whatsappEnabled = true;
            }

            if ($options['send_invite']) {
                $invite = $this->teacherUserInviteService->send($user);
                $inviteStatus = $invite['status'];

                if ($invite['status'] === 'manual') {
                    $manualInvite = [
                        'name' => (string) $user->name,
                        'phone' => (string) $user->phone,
                        'message' => $invite['message'],
                    ];
                }
            }
        });

        return [
            'failed' => false,
            'error' => null,
            'created' => $wasCreated,
            'updated' => $wasUpdated,
            'assigned_to_class' => $assignedToClass,
            'no_class_matched' => $noClassMatched,
            'class_assignment_skipped' => $classAssignmentSkipped,
            'duplicate_email_updated' => $wasUpdated,
            'whatsapp_enabled' => $whatsappEnabled,
            'invite_status' => $inviteStatus,
            'manual_invite' => $manualInvite,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{failed:bool,error:string,created:bool,updated:bool,assigned_to_class:bool,no_class_matched:bool,class_assignment_skipped:bool,duplicate_email_updated:bool,whatsapp_enabled:bool,invite_status:?string,manual_invite:?array{name:string,phone:string,message:string},warnings:array<int, string>}
     */
    private function failedRow(string $message): array
    {
        return [
            'failed' => true,
            'error' => $message,
            'created' => false,
            'updated' => false,
            'assigned_to_class' => false,
            'no_class_matched' => false,
            'class_assignment_skipped' => false,
            'duplicate_email_updated' => false,
            'whatsapp_enabled' => false,
            'invite_status' => null,
            'manual_invite' => null,
            'warnings' => [],
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, int>
     */
    private function buildHeaderMap(array $row): array
    {
        $map = [];

        foreach ($row as $index => $value) {
            $header = trim((string) $value);
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
            $map[mb_strtolower($header)] = $index;
        }

        foreach (self::REQUIRED_COLUMNS as $column) {
            $key = mb_strtolower($column);

            if (! array_key_exists($key, $map)) {
                throw new InvalidArgumentException('The CSV file must include these columns: Name, Phone, Email, Group, Class.');
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function buildClassMap(): array
    {
        return Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->pluck('class_name')
            ->mapWithKeys(fn ($className) => [
                $this->normalizeClassKey((string) $className) => (string) $className,
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function buildClassOwnerMap(): array
    {
        return User::query()
            ->where('role', 'teacher')
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->get(['id', 'class_name'])
            ->mapWithKeys(fn (User $user) => [
                $this->normalizeClassKey((string) $user->class_name) => (int) $user->id,
            ])
            ->all();
    }

    private function findPhoneConflict(string $normalizedPhone, ?int $ignoreUserId = null): ?User
    {
        return User::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->when($ignoreUserId !== null, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->whereIn('phone', MalaysianPhone::variants($normalizedPhone))
            ->first();
    }

    private function normalizeClassKey(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function cell(array $row, int $index): string
    {
        return trim((string) ($row[$index] ?? ''));
    }
}
