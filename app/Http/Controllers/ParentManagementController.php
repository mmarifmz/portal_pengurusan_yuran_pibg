<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\ParentLoginAudit;
use App\Models\Role;
use App\Models\SocialTag;
use App\Models\Student;
use App\Models\User;
use App\Models\UserChangeAudit;
use App\Services\ParentAccountService;
use App\Services\SocialTagService;
use App\Services\TeacherRoleAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParentManagementController extends Controller
{
    public function __construct(
        private readonly ParentAccountService $parentAccountService,
        private readonly SocialTagService $socialTagService,
        private readonly TeacherRoleAssignmentService $teacherRoleAssignmentService
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('manageParentManagement');

        $search = trim((string) $request->string('q')->toString());
        $selectedClass = trim((string) $request->string('class_name')->toString());
        $paymentStatus = trim((string) $request->string('payment_status', 'all')->toString());
        $accountFilter = trim((string) $request->string('account_filter', 'all')->toString());
        $accessFilter = trim((string) $request->string('access_filter', 'all')->toString());
        $roleFilter = trim((string) $request->string('role_filter', 'all')->toString());

        $classOptions = Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->values();

        $latestBillingsByFamily = $this->latestBillingsByFamily();
        $studentsByFamily = Student::query()
            ->active()
            ->orderBy('full_name')
            ->get()
            ->groupBy(fn (Student $student): string => trim((string) $student->family_code));

        $activityByUser = ParentLoginAudit::tableIsAvailable()
            ? ParentLoginAudit::orderByMostRecent(
                ParentLoginAudit::query()->whereNotNull('user_id')
            )->get()->groupBy('user_id')
            : collect();

        $parentUsers = User::query()
            ->withRole('parent')
            ->orderBy('name')
            ->get()
            ->loadMissing('roles');

        $coveredFamilyCodes = collect();

        $accountRows = $parentUsers->map(function (User $user) use ($latestBillingsByFamily, $activityByUser, &$coveredFamilyCodes): array {
            $linkedStudents = $this->parentAccountService->resolvedLinkedStudents($user);
            $familyCodes = $this->parentAccountService->accessibleFamilyCodesForUser($user);
            $coveredFamilyCodes = $coveredFamilyCodes->merge($familyCodes);

            $latestBillings = $familyCodes
                ->map(fn (string $familyCode) => $latestBillingsByFamily->get($familyCode))
                ->filter();

            $activities = $activityByUser->get($user->id, collect());
            $loginRecord = $activities
                ->first(fn (ParentLoginAudit $audit): bool => (($audit->action_type ?? 'login') === 'login'));

            return [
                'row_key' => 'user-'.$user->id,
                'user_id' => $user->id,
                'has_account' => true,
                'name' => $user->name,
                'phone' => (string) ($user->phone ?? ''),
                'email' => (string) ($user->email ?? ''),
                'linked_students' => $linkedStudents->pluck('full_name')->filter()->values()->all(),
                'class_names' => $linkedStudents->pluck('class_name')->filter()->unique()->sort()->values()->all(),
                'payment_status' => $this->summarizePaymentStatus($latestBillings),
                'access_status' => $user->is_active === false ? 'blocked' : 'active',
                'role_names' => $user->roleNames(),
                'last_login_at' => $loginRecord?->occurred_at_for_display,
                'last_activity_at' => $activities->first()?->occurred_at_for_display,
                'detail_url' => route('teacher.parent-management.show', $user),
                'family_codes' => $familyCodes->values()->all(),
            ];
        });

        $unresolvedRows = $latestBillingsByFamily
            ->reject(fn (FamilyBilling $billing, string $familyCode): bool => $coveredFamilyCodes->contains($familyCode))
            ->map(function (FamilyBilling $billing, string $familyCode) use ($studentsByFamily): array {
                $students = $studentsByFamily->get($familyCode, collect());
                $firstStudent = $students->first();

                return [
                    'row_key' => 'family-'.$familyCode,
                    'user_id' => null,
                    'has_account' => false,
                    'name' => (string) ($firstStudent?->parent_name ?: 'No account'),
                    'phone' => (string) ($firstStudent?->parent_phone ?? ''),
                    'email' => (string) ($firstStudent?->parent_email ?? ''),
                    'linked_students' => $students->pluck('full_name')->filter()->values()->all(),
                    'class_names' => $students->pluck('class_name')->filter()->unique()->sort()->values()->all(),
                    'payment_status' => $this->summarizePaymentStatus(collect([$billing])),
                    'access_status' => 'no_account',
                    'role_names' => [],
                    'last_login_at' => null,
                    'last_activity_at' => null,
                    'detail_url' => filled($familyCode) ? route('teacher.records.family', ['familyCode' => $familyCode]) : null,
                    'family_codes' => [$familyCode],
                ];
            })
            ->values();

        $rows = $accountRows
            ->merge($unresolvedRows)
            ->filter(function (array $row) use ($search, $selectedClass, $paymentStatus, $accountFilter, $accessFilter, $roleFilter): bool {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['name'] ?? ''),
                    (string) ($row['phone'] ?? ''),
                    (string) ($row['email'] ?? ''),
                    implode(' ', $row['linked_students'] ?? []),
                    implode(' ', $row['class_names'] ?? []),
                ]));

                if ($search !== '' && ! str_contains($haystack, mb_strtolower($search))) {
                    return false;
                }

                if ($selectedClass !== '' && ! collect($row['class_names'] ?? [])->contains($selectedClass)) {
                    return false;
                }

                if ($paymentStatus !== 'all' && (string) ($row['payment_status'] ?? 'unpaid') !== $paymentStatus) {
                    return false;
                }

                if ($accountFilter === 'has_account' && ! (bool) ($row['has_account'] ?? false)) {
                    return false;
                }

                if ($accountFilter === 'no_account' && (bool) ($row['has_account'] ?? false)) {
                    return false;
                }

                if ($accessFilter !== 'all' && (string) ($row['access_status'] ?? '') !== $accessFilter) {
                    return false;
                }

                $roles = collect($row['role_names'] ?? []);
                if ($roleFilter === 'parent_only' && ! ($roles->contains('parent') && ! $roles->contains(fn (string $role): bool => $role !== 'parent'))) {
                    return false;
                }

                if ($roleFilter === 'dual_role' && ! ($roles->contains('parent') && $roles->count() > 1)) {
                    return false;
                }

                return true;
            })
            ->sortBy([
                ['has_account', 'desc'],
                ['name', 'asc'],
            ])
            ->values();

        return view('teacher.parent-management.index', [
            'rows' => $rows,
            'search' => $search,
            'selectedClass' => $selectedClass,
            'paymentStatus' => $paymentStatus,
            'accountFilter' => $accountFilter,
            'accessFilter' => $accessFilter,
            'roleFilter' => $roleFilter,
            'classOptions' => $classOptions,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        Gate::authorize('manageParentManagement');
        abort_unless($user->hasRole('parent'), 404);

        $user->loadMissing('roles');
        if (User::parentStudentLinksTableAvailable()) {
            $user->loadMissing('parentStudentLinks');
        }

        $linkedStudents = $this->parentAccountService->resolvedLinkedStudents($user);
        $explicitLinks = User::parentStudentLinksTableAvailable()
            ? $user->parentStudentLinks()->get()->keyBy('student_id')
            : collect();
        $familyCodes = $this->parentAccountService->accessibleFamilyCodesForUser($user);
        $latestBillings = $this->latestBillingsByFamily()
            ->only($familyCodes->all())
            ->values();
        $recentPayments = FamilyPaymentTransaction::query()
            ->with('familyBilling:id,family_code,billing_year,status,fee_amount,paid_amount')
            ->whereHas('familyBilling', fn ($query) => $query->whereIn('family_code', $familyCodes->all()))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get();
        $recentActivities = ParentLoginAudit::tableIsAvailable()
            ? ParentLoginAudit::orderByMostRecent(
                ParentLoginAudit::query()->where('user_id', $user->id)
            )->limit(20)->get()
            : collect();
        $availableStudents = Student::query()
            ->active()
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get(['id', 'family_code', 'full_name', 'class_name']);
        $activeSocialTags = $this->socialTagService->activeTags();

        $socialTagIds = $latestBillings
            ->flatMap(fn (FamilyBilling $billing) => $billing->socialTags)
            ->pluck('id')
            ->unique()
            ->values();
        $currentSocialTagId = $socialTagIds->count() === 1
            ? (int) $socialTagIds->first()
            : null;

        $lastLoginAt = $recentActivities
            ->first(fn (ParentLoginAudit $audit): bool => (($audit->action_type ?? 'login') === 'login'))
            ?->occurred_at_for_display;

        return view('teacher.parent-management.show', [
            'parentUser' => $user,
            'linkedStudents' => $linkedStudents,
            'explicitLinks' => $explicitLinks,
            'familyCodes' => $familyCodes,
            'latestBillings' => $latestBillings,
            'recentPayments' => $recentPayments,
            'recentActivities' => $recentActivities,
            'availableStudents' => $availableStudents,
            'activeSocialTags' => $activeSocialTags,
            'currentSocialTagId' => $currentSocialTagId,
            'lastLoginAt' => $lastLoginAt,
            'lastActivityAt' => $recentActivities->first()?->occurred_at_for_display,
            'roleOptions' => [
                'parent' => 'Parent',
                'teacher' => 'Teacher',
                'admin' => 'Admin',
                'super_admin' => 'Super Admin',
            ],
        ]);
    }

    public function updateContact(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageParentManagement');
        abort_unless($user->hasRole('parent'), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $match = $this->teacherRoleAssignmentService->matchExistingUser(
            (string) ($validated['email'] ?? ''),
            (string) ($validated['phone'] ?? ''),
            $user->id
        );

        if ($match['conflict'] !== null || $match['user'] !== null) {
            throw ValidationException::withMessages([
                'email' => $match['conflict'] ?: 'The provided phone or email already belongs to another user.',
            ]);
        }

        $before = [
            'name' => (string) $user->getRawOriginal('name'),
            'phone' => (string) $user->getRawOriginal('phone'),
            'email' => (string) $user->getRawOriginal('email'),
        ];

        $user->forceFill([
            'name' => trim((string) $validated['name']),
            'phone' => filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null,
            'email' => filled($validated['email'] ?? null) ? mb_strtolower(trim((string) $validated['email'])) : null,
        ])->save();

        $this->auditChange($request->user(), $user, 'contact_profile', $before, [
            'name' => (string) $user->getRawOriginal('name'),
            'phone' => (string) $user->getRawOriginal('phone'),
            'email' => (string) $user->getRawOriginal('email'),
        ]);

        return redirect()
            ->route('teacher.parent-management.show', $user)
            ->with('status', 'Parent contact profile updated.');
    }

    public function autosaveSettings(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manageParentManagement');
        abort_unless($user->hasRole('parent'), 404);

        $validated = $request->validate([
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'in:parent,teacher,admin,super_admin'],
            'is_active' => ['sometimes', 'boolean'],
            'access_block_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'social_tag_id' => ['sometimes', 'nullable', 'integer', 'exists:social_tags,id'],
        ]);

        $updatedFields = [];
        $warnings = [];

        if ($request->has('roles')) {
            $before = $user->roleNames();
            $this->syncManagedRoles($user, collect($validated['roles'] ?? []));
            $updatedFields[] = 'roles';
            $this->auditChange($request->user(), $user, 'roles', $before, $user->fresh()->roleNames());
        }

        if ($request->has('is_active')) {
            $before = (bool) $user->is_active;
            $user->forceFill([
                'is_active' => (bool) $validated['is_active'],
            ])->save();
            $updatedFields[] = 'is_active';
            $this->auditChange($request->user(), $user, 'is_active', $before, (bool) $user->is_active);
        }

        if ($request->exists('access_block_reason')) {
            if (User::hasUserColumn('access_block_reason')) {
                $before = (string) ($user->access_block_reason ?? '');
                $user->forceFill([
                    'access_block_reason' => filled($validated['access_block_reason'] ?? null)
                        ? trim((string) $validated['access_block_reason'])
                        : null,
                ])->save();
                $updatedFields[] = 'access_block_reason';
                $this->auditChange($request->user(), $user, 'access_block_reason', $before, (string) ($user->access_block_reason ?? ''));
            } else {
                $warnings[] = 'Block reason requires the latest parent-management migration.';
            }
        }

        if ($request->exists('social_tag_id')) {
            $familyCodes = $this->parentAccountService->accessibleFamilyCodesForUser($user);
            $billings = $this->latestBillingsByFamily()->only($familyCodes->all());
            $before = $billings
                ->flatMap(fn (FamilyBilling $billing) => $billing->socialTags->pluck('id'))
                ->unique()
                ->values()
                ->all();

            foreach ($billings as $billing) {
                $billing->socialTags()->sync(filled($validated['social_tag_id'] ?? null) ? [(int) $validated['social_tag_id']] : []);
                $billing->load('socialTags');
                $this->socialTagService->syncFamilyPrimarySocialTag($billing);
            }

            $updatedFields[] = 'social_tag_id';
            $this->auditChange(
                $request->user(),
                $user,
                'social_tag_id',
                $before,
                filled($validated['social_tag_id'] ?? null) ? [(int) $validated['social_tag_id']] : []
            );
        }

        return response()->json([
            'status' => 'saved',
            'updated_fields' => $updatedFields,
            'warnings' => $warnings,
        ]);
    }

    public function syncStudentLinks(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageParentManagement');
        abort_unless($user->hasRole('parent'), 404);

        if (! User::parentStudentLinksTableAvailable()) {
            return redirect()
                ->route('teacher.parent-management.show', $user)
                ->withErrors([
                    'student_ids' => 'Explicit parent-student linking requires the latest parent-management migration.',
                ]);
        }

        $validated = $request->validate([
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $selectedStudentIds = collect($validated['student_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $before = $user->parentStudentLinks()
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $existingLinks = $user->parentStudentLinks()
            ->get()
            ->keyBy('student_id');

        $syncPayload = $selectedStudentIds
            ->mapWithKeys(function (int $studentId) use ($existingLinks, $request): array {
                $existing = $existingLinks->get($studentId);

                return [
                    $studentId => [
                        'relationship_type' => 'guardian',
                        'notes' => (string) ($existing?->notes ?? ''),
                        'linked_by_user_id' => $existing?->linked_by_user_id ?? $request->user()?->id,
                        'linked_at' => $existing?->linked_at ?? now(),
                    ],
                ];
            })
            ->all();

        $user->linkedStudents()->sync($syncPayload);

        $this->auditChange($request->user(), $user, 'student_links', $before, $selectedStudentIds->all());

        return redirect()
            ->route('teacher.parent-management.show', $user)
            ->with('status', 'Linked students updated.');
    }

    public function updateStudentLink(Request $request, User $user, Student $student): JsonResponse
    {
        Gate::authorize('manageParentManagement');
        abort_unless($user->hasRole('parent'), 404);

        if (! User::parentStudentLinksTableAvailable()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Explicit parent-student notes require the latest parent-management migration.',
            ], 409);
        }

        $pivot = $user->parentStudentLinks()->where('student_id', $student->id)->first();
        $before = (string) ($pivot?->notes ?? '');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user->linkedStudents()->syncWithoutDetaching([
            $student->id => [
                'relationship_type' => 'guardian',
                'notes' => trim((string) ($validated['notes'] ?? '')),
                'linked_by_user_id' => $request->user()?->id,
                'linked_at' => $pivot?->linked_at ?? now(),
            ],
        ]);

        $pivot = $user->parentStudentLinks()->where('student_id', $student->id)->firstOrFail();
        $pivot->forceFill([
            'notes' => trim((string) ($validated['notes'] ?? '')),
        ])->save();

        $this->auditChange($request->user(), $user, 'student_link_note:'.$student->id, $before, (string) ($pivot->notes ?? ''));

        return response()->json([
            'status' => 'saved',
        ]);
    }

    public function resetAccess(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageParentManagement');
        abort_unless($user->hasRole('parent'), 404);

        if (! User::hasUserColumn('parent_access_reset_at')) {
            return redirect()
                ->route('teacher.parent-management.show', $user)
                ->withErrors([
                    'access' => 'Access reset requires the latest parent-management migration.',
                ]);
        }

        $before = $user->parent_access_reset_at;

        $user->forceFill([
            'parent_access_reset_at' => now(),
        ])->save();

        $this->auditChange($request->user(), $user, 'parent_access_reset_at', $before?->toIso8601String(), $user->parent_access_reset_at?->toIso8601String());

        return redirect()
            ->route('teacher.parent-management.show', $user)
            ->with('status', 'Parent access has been reset. TAC verification will be required again.');
    }

    /**
     * @return Collection<string, FamilyBilling>
     */
    private function latestBillingsByFamily(): Collection
    {
        $latestIds = FamilyBilling::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('family_code')
            ->pluck('id');

        return FamilyBilling::query()
            ->with('socialTags')
            ->whereIn('id', $latestIds)
            ->get()
            ->keyBy(fn (FamilyBilling $billing): string => trim((string) $billing->family_code));
    }

    private function summarizePaymentStatus(Collection $billings): string
    {
        if ($billings->isEmpty()) {
            return 'unpaid';
        }

        if ($billings->every(fn (FamilyBilling $billing): bool => $billing->outstanding_amount <= 0)) {
            return 'paid';
        }

        if ($billings->contains(fn (FamilyBilling $billing): bool => (float) $billing->paid_amount > 0)) {
            return 'partial';
        }

        return 'unpaid';
    }

    private function syncManagedRoles(User $user, Collection $requestedRoles): void
    {
        $requestedRoles = $requestedRoles
            ->map(fn ($role): string => trim((string) $role))
            ->filter()
            ->unique()
            ->values();

        $managedRoles = collect(['parent', 'teacher', 'admin', 'super_admin']);
        $existingRoles = collect($user->roleNames());
        $preservedRoles = $existingRoles
            ->reject(fn (string $role): bool => $managedRoles->contains($role))
            ->values();

        $desiredRoles = $preservedRoles
            ->merge($requestedRoles)
            ->unique()
            ->values();

        if ($desiredRoles->isEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'At least one role must remain assigned.',
            ]);
        }

        $roleIds = $desiredRoles
            ->map(function (string $role): int {
                return (int) Role::query()->firstOrCreate(['name' => $role])->id;
            })
            ->all();

        DB::transaction(function () use ($user, $roleIds, $desiredRoles): void {
            $user->roles()->sync($roleIds);
            $user->forceFill([
                'role' => $this->resolvePrimaryRole($desiredRoles, (string) $user->role),
            ])->save();
        });
    }

    private function resolvePrimaryRole(Collection $roles, string $currentRole): string
    {
        if ($roles->contains($currentRole)) {
            return $currentRole;
        }

        foreach (['super_admin', 'admin', 'system_admin', 'teacher', 'parent'] as $candidate) {
            if ($roles->contains($candidate)) {
                return $candidate;
            }
        }

        return (string) $roles->first();
    }

    private function auditChange(?User $adminUser, User $affectedUser, string $field, mixed $oldValue, mixed $newValue): void
    {
        if (! UserChangeAudit::tableIsAvailable()) {
            return;
        }

        UserChangeAudit::query()->create([
            'admin_user_id' => $adminUser?->id,
            'affected_user_id' => $affectedUser->id,
            'field_changed' => $field,
            'old_value' => $this->serializeAuditValue($oldValue),
            'new_value' => $this->serializeAuditValue($newValue),
            'changed_at' => now(),
        ]);
    }

    private function serializeAuditValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
