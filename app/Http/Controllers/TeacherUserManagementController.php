<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportTeacherUsersRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\TeacherUserImportService;
use App\Services\TeacherUserInviteService;
use App\Services\TeacherRoleAssignmentService;
use App\Support\MalaysianPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherUserManagementController extends Controller
{
    public function __construct(
        private readonly TeacherUserInviteService $teacherUserInviteService,
        private readonly TeacherRoleAssignmentService $teacherRoleAssignmentService
    ) {
    }

    public function index(Request $request): View
    {
        $existingUserSearch = trim((string) $request->query('existing_user_search', ''));
        $classOptions = Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->values();

        $teacherUsers = User::query()
            ->withRole('teacher')
            ->orderBy('name')
            ->get()
            ->loadMissing('roles');

        $existingUserMatches = $existingUserSearch !== ''
            ? $this->searchAssignableUsers($existingUserSearch)
            : collect();

        return view('teacher.users', [
            'teacherUsers' => $teacherUsers,
            'classOptions' => $classOptions,
            'existingUserSearch' => $existingUserSearch,
            'existingUserMatches' => $existingUserMatches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $allowedClasses = $this->allowedClassNames();
        $normalizedPhone = null;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:120'],
            'phone' => $this->phoneFormatRules($normalizedPhone),
            'class_name' => ['nullable', 'string', Rule::in($allowedClasses)],
            'receive_whatsapp_notifications' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $match = $this->teacherRoleAssignmentService->matchExistingUser(
            (string) $validated['email'],
            $normalizedPhone,
        );

        if ($match['conflict'] !== null) {
            throw ValidationException::withMessages([
                'email' => $match['conflict'],
            ]);
        }

        $className = $validated['class_name'] ?: null;
        $receiveWhatsappNotifications = $this->resolveWhatsappPreference(
            $className,
            $normalizedPhone,
            (bool) ($validated['receive_whatsapp_notifications'] ?? false)
        );

        /** @var User|null $existingUser */
        $existingUser = $match['user'];
        if ($existingUser !== null) {
            if ($existingUser->hasRole('teacher')) {
                throw ValidationException::withMessages([
                    'email' => 'A teacher account with this email or WhatsApp number already exists.',
                ]);
            }

            $result = $this->teacherRoleAssignmentService->assignTeacherRole(
                $existingUser,
                [
                    'name' => $validated['name'],
                    'email' => mb_strtolower(trim((string) $validated['email'])),
                    'phone' => $normalizedPhone,
                    'class_name' => $className,
                    'enable_whatsapp' => $receiveWhatsappNotifications,
                    'name_update_mode' => 'if_blank',
                    'matched_by' => $match['matched_by'],
                ],
                $request->user(),
                'manual'
            );

            return redirect()
                ->route('super-teacher.teachers.index')
                ->with('status', sprintf(
                    'Existing user %s was upgraded with Teacher access%s.',
                    $result['user']->name,
                    $className ? " and assigned to {$className}" : ''
                ));
        }

        if (blank($validated['password'] ?? null)) {
            throw ValidationException::withMessages([
                'password' => 'A password is required only when creating a brand new teacher account.',
            ]);
        }

        $teacher = User::create([
            'name' => $validated['name'],
            'email' => mb_strtolower(trim((string) $validated['email'])),
            'phone' => $normalizedPhone,
            'role' => 'teacher',
            'class_name' => $className,
            'is_active' => true,
            'receive_whatsapp_notifications' => $receiveWhatsappNotifications,
            'invite_status' => 'pending',
            'password' => $validated['password'],
        ]);
        $teacher->assignRole('teacher');

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', 'Teacher user created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole('teacher'), 404);

        $allowedClasses = $this->allowedClassNames();
        $normalizedPhone = null;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => $this->phoneRules($normalizedPhone, $user),
            'class_name' => ['nullable', 'string', Rule::in($allowedClasses)],
            'receive_whatsapp_notifications' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $className = $validated['class_name'] ?: null;
        $receiveWhatsappNotifications = $this->resolveWhatsappPreference(
            $className,
            $normalizedPhone,
            (bool) ($validated['receive_whatsapp_notifications'] ?? false)
        );

        $user->name = $validated['name'];
        $user->email = mb_strtolower(trim((string) $validated['email']));
        $user->phone = $normalizedPhone;
        $user->class_name = $className;
        $user->receive_whatsapp_notifications = $receiveWhatsappNotifications;

        if (filled($validated['password'] ?? null)) {
            $user->password = (string) $validated['password'];
        }

        $user->save();

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', 'Teacher user updated successfully.');
    }

    public function import(ImportTeacherUsersRequest $request, TeacherUserImportService $teacherUserImportService): RedirectResponse
    {
        $file = $request->file('teachers_csv');

        if ($file === null) {
            throw ValidationException::withMessages([
                'teachers_csv' => 'Please upload a CSV file.',
            ]);
        }

        try {
            $summary = $teacherUserImportService->import(
                $file->getRealPath(),
                [
                    'assign_class' => (bool) $request->boolean('auto_assign_class'),
                    'enable_whatsapp' => (bool) $request->boolean('enable_whatsapp_notifications'),
                    'send_invite' => (bool) $request->boolean('send_teacher_invites'),
                ],
                $request->user(),
                $file->getClientOriginalName(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'teachers_csv' => $exception->getMessage(),
            ]);
        }

        $request->session()->put('teacher_import_summary', $summary);
        $request->session()->put('teacher_manual_invites', $summary['manual_invites']);

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', "Teacher import completed. {$summary['created']} created, {$summary['updated']} updated, {$summary['failed_rows_count']} failed.");
    }

    public function downloadSampleCsv(): StreamedResponse
    {
        $rows = [
            ['Name', 'Phone', 'Email', 'Group', 'Class'],
            ['SSP 4 Angsana - Cikgu Farahani', '+60139906160', 'cikgu.farahani@sripetaling.edu.my', 'TAHAP 2', '4 ANGSANA'],
            ['SSP 4 Alamanda - Cikgu Zairi', '+601128509436', 'cikgu.zairi@sripetaling.edu.my', 'TAHAP 2', '4 ALAMANDA'],
            ['SSP 4 Akasia - Puan Tan', '+60129772410', 'puan.tan@sripetaling.edu.my', 'TAHAP 2', '4 AKASIA'],
        ];

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, 'teacher-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadFailedRows(Request $request): StreamedResponse
    {
        $summary = $request->session()->get('teacher_import_summary');
        $failedRows = is_array($summary) ? ($summary['failed_rows'] ?? []) : [];

        if (! is_array($failedRows) || $failedRows === []) {
            throw ValidationException::withMessages([
                'teachers_csv' => 'No failed-row CSV is available yet. Run an import first, then download the latest failed rows.',
            ]);
        }

        return response()->streamDownload(function () use ($failedRows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, ['Row', 'Name', 'Phone', 'Email', 'Group', 'Class', 'Error']);

            foreach ($failedRows as $row) {
                fputcsv($output, [
                    $row['row_number'] ?? '',
                    $row['name'] ?? '',
                    $row['phone'] ?? '',
                    $row['email'] ?? '',
                    $row['group'] ?? '',
                    $row['class'] ?? '',
                    $row['error'] ?? '',
                ]);
            }

            fclose($output);
        }, 'teacher-import-failed-rows.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole('teacher'), 404);

        if ($request->user()?->id === $user->id) {
            throw ValidationException::withMessages([
                'delete' => 'You cannot delete your own account.',
            ]);
        }

        $user->delete();

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', 'Teacher user deleted successfully.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole('teacher'), 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if ($request->user()?->id === $user->id && ! (bool) $validated['enabled']) {
            throw ValidationException::withMessages([
                'enabled' => 'You cannot disable your own account.',
            ]);
        }

        $user->is_active = (bool) $validated['enabled'];
        $user->save();

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', $user->is_active ? 'Teacher account enabled.' : 'Teacher account disabled.');
    }

    public function updateWhatsappNotifications(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole('teacher'), 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $wantsEnabled = (bool) $validated['enabled'];
        if ($wantsEnabled && ! $this->isWhatsappEligible($user)) {
            throw ValidationException::withMessages([
                'enabled' => 'WhatsApp notifications can only be enabled for active class teachers with a valid WhatsApp number.',
            ]);
        }

        $user->receive_whatsapp_notifications = $wantsEnabled;
        $user->save();

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', $wantsEnabled
                ? 'WhatsApp notifications enabled for this teacher.'
                : 'WhatsApp notifications disabled for this teacher.');
    }

    public function enableWhatsappForAllAssignedTeachers(): RedirectResponse
    {
        $teachers = User::query()
            ->withRole('teacher')
            ->orderBy('name')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($teachers as $teacher) {
            if (! $this->isWhatsappEligible($teacher) || $teacher->receive_whatsapp_notifications) {
                $skipped++;

                continue;
            }

            $teacher->forceFill([
                'receive_whatsapp_notifications' => true,
            ])->save();

            $updated++;
        }

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', "WhatsApp enabled for {$updated} assigned teachers. Skipped {$skipped}.");
    }

    public function disableWhatsappForAll(): RedirectResponse
    {
        $teachers = User::query()
            ->withRole('teacher')
            ->orderBy('name')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($teachers as $teacher) {
            if (! $teacher->receive_whatsapp_notifications) {
                $skipped++;

                continue;
            }

            $teacher->forceFill([
                'receive_whatsapp_notifications' => false,
            ])->save();

            $updated++;
        }

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', "WhatsApp disabled for {$updated} teachers. Skipped {$skipped}.");
    }

    public function sendInvite(User $user): RedirectResponse
    {
        abort_unless($user->hasRole('teacher'), 404);

        $result = $this->teacherUserInviteService->send($user);

        if ($result['status'] === 'failed') {
            throw ValidationException::withMessages([
                'invite' => $result['error'] ?? 'Unable to send the teacher invite.',
            ]);
        }

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', $result['status'] === 'sent'
                ? "Invite sent to {$user->name}."
                : "Invite prepared for manual sending to {$user->name}.")
            ->with('teacher_manual_invites', $result['status'] === 'manual'
                ? [[
                    'name' => (string) $user->name,
                    'phone' => (string) $user->phone,
                    'message' => $result['message'],
                ]]
                : []);
    }

    public function sendInviteToAllActiveTeachers(): RedirectResponse
    {
        $teachers = User::query()
            ->withRole('teacher')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $sent = 0;
        $manual = 0;
        $failed = 0;
        $skipped = 0;
        $manualInvites = [];

        foreach ($teachers as $teacher) {
            if (blank($teacher->phone)) {
                $skipped++;

                continue;
            }

            $result = $this->teacherUserInviteService->send($teacher);

            if ($result['status'] === 'sent') {
                $sent++;

                continue;
            }

            if ($result['status'] === 'manual') {
                $manual++;
                $manualInvites[] = [
                    'name' => (string) $teacher->name,
                    'phone' => (string) $teacher->phone,
                    'message' => $result['message'],
                ];

                continue;
            }

            $failed++;
        }

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', "Teacher invites processed. Sent {$sent}, manual {$manual}, failed {$failed}, skipped {$skipped}.")
            ->with('teacher_manual_invites', $manualInvites);
    }

    public function assignExisting(Request $request): RedirectResponse
    {
        $allowedClasses = $this->allowedClassNames();

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'class_name' => ['required', 'string', Rule::in($allowedClasses)],
            'enable_whatsapp_notifications' => ['nullable', 'boolean'],
            'send_teacher_invite' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->findOrFail((int) $validated['user_id']);
        $className = trim((string) $validated['class_name']);

        $existingClassTeacher = User::query()
            ->withRole('teacher')
            ->where('class_name', $className)
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingClassTeacher !== null) {
            throw ValidationException::withMessages([
                'class_name' => "The class {$className} is already assigned to another teacher user.",
            ]);
        }

        $result = $this->teacherRoleAssignmentService->assignTeacherRole(
            $user,
            [
                'class_name' => $className,
                'enable_whatsapp' => (bool) $request->boolean('enable_whatsapp_notifications'),
                'name_update_mode' => 'never',
            ],
            $request->user(),
            'manual'
        );

        $manualInvites = [];
        if ($request->boolean('send_teacher_invite')) {
            $invite = $this->teacherUserInviteService->send($result['user']);

            if ($invite['status'] === 'failed') {
                throw ValidationException::withMessages([
                    'send_teacher_invite' => $invite['error'] ?? 'Unable to send the teacher invite.',
                ]);
            }

            if ($invite['status'] === 'manual') {
                $manualInvites[] = [
                    'name' => (string) $result['user']->name,
                    'phone' => (string) $result['user']->phone,
                    'message' => $invite['message'],
                ];
            }
        }

        return redirect()
            ->route('super-teacher.teachers.index', ['existing_user_search' => (string) $request->input('existing_user_search', '')])
            ->with('status', sprintf(
                'Existing user %s is now assigned as class teacher for %s.',
                $result['user']->name,
                $className
            ))
            ->with('teacher_manual_invites', $manualInvites);
    }

    /**
     * @return array<int, string>
     */
    private function allowedClassNames(): array
    {
        return Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->pluck('class_name')
            ->map(fn ($className) => (string) $className)
            ->values()
            ->all();
    }

    /**
     * @param  ?string  $normalizedPhone
     * @return array<int, mixed>
     */
    private function phoneRules(?string &$normalizedPhone, ?User $ignoreUser = null): array
    {
        return [
            'nullable',
            'string',
            'max:25',
            function (string $attribute, mixed $value, \Closure $fail) use (&$normalizedPhone, $ignoreUser): void {
                $normalizedPhone = MalaysianPhone::normalize($value);

                if ($value !== null && trim((string) $value) !== '' && $normalizedPhone === null) {
                    $fail('Please enter a valid Malaysian WhatsApp number (e.g. +60123456789 or 0123456789).');

                    return;
                }

                if ($normalizedPhone === null) {
                    return;
                }

                if ($this->findPhoneConflict($normalizedPhone, $ignoreUser?->id) !== null) {
                    $fail('This WhatsApp number is already used by another user.');
                }
            },
        ];
    }

    /**
     * @param  ?string  $normalizedPhone
     * @return array<int, mixed>
     */
    private function phoneFormatRules(?string &$normalizedPhone): array
    {
        return [
            'nullable',
            'string',
            'max:25',
            function (string $attribute, mixed $value, \Closure $fail) use (&$normalizedPhone): void {
                $normalizedPhone = MalaysianPhone::normalize($value);

                if ($value !== null && trim((string) $value) !== '' && $normalizedPhone === null) {
                    $fail('Please enter a valid Malaysian WhatsApp number (e.g. +60123456789 or 0123456789).');
                }
            },
        ];
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

    private function resolveWhatsappPreference(?string $className, ?string $phone, bool $requested): bool
    {
        if (blank($className) || blank($phone)) {
            return false;
        }

        return $requested;
    }

    private function isWhatsappEligible(User $user): bool
    {
        return $user->is_active
            && filled($user->class_name)
            && filled($user->phone);
    }

    private function searchAssignableUsers(string $keyword)
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return collect();
        }

        return User::query()
            ->where(function ($query) use ($keyword): void {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            })
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->loadMissing('roles')
            ->reject(fn (User $user): bool => $user->hasRole('teacher'))
            ->values();
    }
}
