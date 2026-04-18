<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeacherUserManagementController extends Controller
{
    public function index(): View
    {
        $classOptions = Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->values();

        $teacherUsers = User::query()
            ->where('role', 'teacher')
            ->orderBy('name')
            ->get();

        return view('teacher.users', [
            'teacherUsers' => $teacherUsers,
            'classOptions' => $classOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $allowedClasses = $this->allowedClassNames();
        $normalizedPhone = null;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:120', 'unique:users,email'],
            'phone' => [
                'nullable',
                'string',
                'max:25',
                'unique:users,phone',
                function (string $attribute, mixed $value, \Closure $fail) use (&$normalizedPhone): void {
                    $normalizedPhone = $this->normalizeMalaysianWhatsappPhone($value);
                    if ($value !== null && trim((string) $value) !== '' && $normalizedPhone === null) {
                        $fail('Please enter a valid Malaysian WhatsApp number (e.g. 60123456789 or 0123456789).');
                    }
                },
            ],
            'class_name' => ['nullable', 'string', Rule::in($allowedClasses)],
            'receive_whatsapp_notifications' => ['nullable', 'boolean'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $className = $validated['class_name'] ?: null;
        $receiveWhatsappNotifications = $this->resolveWhatsappPreference(
            $className,
            $normalizedPhone,
            (bool) ($validated['receive_whatsapp_notifications'] ?? false)
        );

        User::create([
            'name' => $validated['name'],
            'email' => mb_strtolower(trim((string) $validated['email'])),
            'phone' => $normalizedPhone,
            'role' => 'teacher',
            'class_name' => $className,
            'is_active' => true,
            'receive_whatsapp_notifications' => $receiveWhatsappNotifications,
            'password' => $validated['password'],
        ]);

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', 'Teacher user created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'teacher', 404);

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
            'phone' => [
                'nullable',
                'string',
                'max:25',
                Rule::unique('users', 'phone')->ignore($user->id),
                function (string $attribute, mixed $value, \Closure $fail) use (&$normalizedPhone): void {
                    $normalizedPhone = $this->normalizeMalaysianWhatsappPhone($value);
                    if ($value !== null && trim((string) $value) !== '' && $normalizedPhone === null) {
                        $fail('Please enter a valid Malaysian WhatsApp number (e.g. 60123456789 or 0123456789).');
                    }
                },
            ],
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

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'teacher', 404);

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
        abort_unless($user->role === 'teacher', 404);

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
        abort_unless($user->role === 'teacher', 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $wantsEnabled = (bool) $validated['enabled'];
        if ($wantsEnabled && (blank($user->class_name) || blank($user->phone))) {
            throw ValidationException::withMessages([
                'enabled' => 'WhatsApp notifications can only be enabled for class teachers with a valid WhatsApp number.',
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

    private function normalizeMalaysianWhatsappPhone(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $sanitized = preg_replace('/[^\d+]/', '', $raw) ?? '';
        if (str_starts_with($sanitized, '+')) {
            $sanitized = ltrim($sanitized, '+');
        }

        if (str_starts_with($sanitized, '0')) {
            $sanitized = '60'.substr($sanitized, 1);
        }

        if (! preg_match('/^601[0-9]\d{7,8}$/', $sanitized)) {
            return null;
        }

        return $sanitized;
    }

    private function resolveWhatsappPreference(?string $className, ?string $phone, bool $requested): bool
    {
        if (blank($className) || blank($phone)) {
            return false;
        }

        return $requested;
    }
}
