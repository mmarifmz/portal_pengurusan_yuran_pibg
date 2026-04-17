<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:120', 'unique:users,email'],
            'class_name' => ['nullable', 'string', Rule::in($allowedClasses)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => mb_strtolower(trim((string) $validated['email'])),
            'role' => 'teacher',
            'class_name' => $validated['class_name'] ?: null,
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'class_name' => ['nullable', 'string', Rule::in($allowedClasses)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $user->name = $validated['name'];
        $user->email = mb_strtolower(trim((string) $validated['email']));
        $user->class_name = $validated['class_name'] ?: null;

        if (filled($validated['password'] ?? null)) {
            $user->password = (string) $validated['password'];
        }

        $user->save();

        return redirect()
            ->route('super-teacher.teachers.index')
            ->with('status', 'Teacher user updated successfully.');
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
}
