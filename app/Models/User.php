<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'role',
    'class_name',
    'is_payment_tester',
    'is_active',
    'access_block_reason',
    'parent_access_reset_at',
    'receive_whatsapp_notifications',
    'teacher_invite_sent_at',
    'invite_status',
    'onboarding_invite_generated_at',
    'onboarding_invite_sent_manually_at',
    'onboarding_invite_sent_by',
    'onboarding_invite_method',
    'onboarding_invite_status',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::created(function (self $user): void {
            $user->syncPrimaryRoleAssignment();
        });

        static::updated(function (self $user): void {
            if ($user->wasChanged('role')) {
                $user->syncPrimaryRoleAssignment();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_payment_tester' => 'boolean',
            'is_active' => 'boolean',
            'parent_access_reset_at' => 'datetime',
            'receive_whatsapp_notifications' => 'boolean',
            'teacher_invite_sent_at' => 'datetime',
            'onboarding_invite_generated_at' => 'datetime',
            'onboarding_invite_sent_manually_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function getNameAttribute(?string $value): string
    {
        $name = trim((string) $value);

        if ($name === '') {
            return '';
        }

        return mb_strtoupper($name);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function parentStudentLinks(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class);
    }

    public function linkedStudents(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student_links')
            ->withPivot(['id', 'relationship_type', 'notes', 'linked_by_user_id', 'linked_at'])
            ->withTimestamps();
    }

    public function changeAuditsAuthored(): HasMany
    {
        return $this->hasMany(UserChangeAudit::class, 'admin_user_id');
    }

    public function changeAuditsReceived(): HasMany
    {
        return $this->hasMany(UserChangeAudit::class, 'affected_user_id');
    }

    public function scopeWithRole(Builder $query, string $role): Builder
    {
        $roles = self::roleAliases($role);

        return $query->where(function (Builder $builder) use ($roles): void {
            $builder
                ->whereIn('users.role', $roles)
                ->orWhereExists(function ($subQuery) use ($roles): void {
                    $subQuery
                        ->selectRaw('1')
                        ->from('user_roles')
                        ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                        ->whereColumn('user_roles.user_id', 'users.id')
                        ->whereIn('roles.name', $roles);
                });
        });
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function scopeWithAnyRole(Builder $query, array $roles): Builder
    {
        $roles = collect($roles)
            ->map(fn ($role): string => trim((string) $role))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($roles === []) {
            return $query;
        }

        $expandedRoles = collect($roles)
            ->flatMap(fn (string $role): array => self::roleAliases($role))
            ->unique()
            ->values()
            ->all();

        return $query->where(function (Builder $builder) use ($expandedRoles): void {
            $builder
                ->whereIn('users.role', $expandedRoles)
                ->orWhereExists(function ($subQuery) use ($expandedRoles): void {
                    $subQuery
                        ->selectRaw('1')
                        ->from('user_roles')
                        ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                        ->whereColumn('user_roles.user_id', 'users.id')
                        ->whereIn('roles.name', $expandedRoles);
                });
        });
    }

    public function hasRole(string $role): bool
    {
        $role = trim($role);

        if ($role === '') {
            return false;
        }

        $aliases = self::roleAliases($role);

        if (in_array((string) $this->role, $aliases, true)) {
            return true;
        }

        if (! $this->roleTablesAvailable()) {
            return false;
        }

        if ($this->relationLoaded('roles')) {
            /** @var Collection<int, Role> $roles */
            $roles = $this->roles;

            return $roles->contains(fn (Role $item): bool => in_array((string) $item->name, $aliases, true));
        }

        return $this->roles()->whereIn('name', $aliases)->exists();
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole((string) $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        $roles = collect();

        if (filled($this->role)) {
            $roles->push((string) $this->role);
        }

        if ($this->roleTablesAvailable()) {
            $pivotRoles = $this->relationLoaded('roles')
                ? $this->roles
                : $this->roles()->get(['roles.name']);

            $roles = $roles->merge(
                $pivotRoles->map(fn ($role): string => (string) $role->name)
            );
        }

        return $roles
            ->map(fn ($role): string => trim((string) $role))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function hasMultipleRoles(): bool
    {
        return count($this->roleNames()) > 1;
    }

    public function assignRole(string $role): void
    {
        $role = trim($role);

        if ($role === '' || ! $this->exists || ! $this->roleTablesAvailable()) {
            return;
        }

        $roleModel = Role::query()->firstOrCreate(['name' => $role]);
        $this->roles()->syncWithoutDetaching([$roleModel->id]);
    }

    public function isParentOnly(): bool
    {
        return $this->hasRole('parent') && ! $this->isStaff();
    }

    public function isTeacher(): bool
    {
        return $this->hasRole('teacher');
    }

    public function isSuperTeacher(): bool
    {
        return $this->hasRole('super_teacher');
    }

    public function isSystemAdmin(): bool
    {
        return $this->hasAnyRole(['system_admin', 'admin', 'super_admin']);
    }

    public function isAdmin(): bool
    {
        return $this->isSystemAdmin();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isSystemInstaller(): bool
    {
        return $this->hasRole('system_installer');
    }

    public function isParent(): bool
    {
        return $this->hasRole('parent');
    }

    public function isPta(): bool
    {
        return $this->hasRole('pta');
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(['teacher', 'super_teacher', 'system_admin', 'admin', 'super_admin', 'pta']);
    }

    public function canAccessTeacherRecords(): bool
    {
        return $this->hasAnyRole(['teacher', 'super_teacher', 'system_admin', 'admin', 'super_admin', 'pta']);
    }

    public function canManageTeacherUsers(): bool
    {
        return $this->hasAnyRole(['super_teacher', 'system_admin', 'admin', 'super_admin']);
    }

    public function canManageParentAccounts(): bool
    {
        return $this->hasAnyRole(['system_admin', 'admin', 'super_admin']);
    }

    public function isParentTester(): bool
    {
        return $this->isParent() && (bool) $this->is_payment_tester;
    }

    public static function onboardingInviteColumnsAvailable(): bool
    {
        return Schema::hasColumn('users', 'onboarding_invite_generated_at')
            && Schema::hasColumn('users', 'onboarding_invite_sent_manually_at')
            && Schema::hasColumn('users', 'onboarding_invite_sent_by')
            && Schema::hasColumn('users', 'onboarding_invite_method')
            && Schema::hasColumn('users', 'onboarding_invite_status');
    }

    public static function hasUserColumn(string $column): bool
    {
        return Schema::hasColumn('users', $column);
    }

    public static function parentStudentLinksTableAvailable(): bool
    {
        return Schema::hasTable('parent_student_links');
    }

    private function syncPrimaryRoleAssignment(): void
    {
        $primaryRole = trim((string) $this->getAttribute('role'));

        if ($primaryRole === '' || ! $this->exists || ! $this->roleTablesAvailable()) {
            return;
        }

        $role = Role::query()->firstOrCreate(['name' => $primaryRole]);
        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    private function roleTablesAvailable(): bool
    {
        return Schema::hasTable('roles') && Schema::hasTable('user_roles');
    }

    /**
     * @return array<int, string>
     */
    private static function roleAliases(string $role): array
    {
        return match (trim($role)) {
            'system_admin', 'admin' => ['system_admin', 'admin', 'super_admin'],
            default => [trim($role)],
        };
    }
}
