<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\ParentStudentLink;
use App\Models\Student;
use App\Models\User;
use App\Support\ParentPhone;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ParentAccountService
{
    public function __construct(
        private readonly TeacherRoleAssignmentService $teacherRoleAssignmentService
    ) {
    }

    public function resolveOrCreateForFamily(string $phone, FamilyBilling $familyBilling, ?User $actor = null): User
    {
        $familyStudents = Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        $parentName = (string) ($familyStudents->firstWhere('parent_name')?->parent_name
            ?? $familyStudents->first()?->parent_name
            ?? "Parent {$familyBilling->family_code}");
        $parentEmail = (string) ($familyStudents->firstWhere('parent_email')?->parent_email
            ?? $familyStudents->pluck('parent_email')->filter()->first()
            ?? '');

        $sanitizedPhone = ParentPhone::sanitizeInput($phone);
        $match = $this->teacherRoleAssignmentService->matchExistingUser(
            $parentEmail !== '' ? $parentEmail : null,
            $sanitizedPhone
        );

        if ($match['conflict'] !== null) {
            throw new DomainException($match['conflict']);
        }

        /** @var User|null $parent */
        $parent = $match['user'];

        DB::transaction(function () use (&$parent, $familyBilling, $familyStudents, $parentName, $parentEmail, $sanitizedPhone, $actor): void {
            if (! $parent) {
                $parent = User::query()->create([
                    'name' => $parentName,
                    'email' => $parentEmail !== ''
                        ? mb_strtolower(trim($parentEmail))
                        : sprintf(
                            'parent-%s-%s@placeholder.local',
                            Str::lower($familyBilling->family_code),
                            preg_replace('/\D+/', '', $sanitizedPhone) ?: Str::lower((string) Str::ulid())
                        ),
                    'phone' => $sanitizedPhone,
                    'role' => 'parent',
                    'password' => Str::random(40),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
            } else {
                if (blank($parent->getRawOriginal('role'))) {
                    $parent->role = 'parent';
                }

                if (blank($parent->getRawOriginal('phone'))) {
                    $parent->phone = $sanitizedPhone;
                }

                if (blank($parent->getRawOriginal('email')) || str_ends_with((string) $parent->email, '@placeholder.local')) {
                    if ($parentEmail !== '') {
                        $parent->email = mb_strtolower(trim($parentEmail));
                    }
                }

                if (blank($parent->getRawOriginal('name'))) {
                    $parent->name = $parentName;
                }

                if ($parent->is_active === null) {
                    $parent->is_active = true;
                }

                $parent->save();
            }

            $parent->assignRole('parent');

            if (! $familyBilling->hasRegisteredPhone($sanitizedPhone)) {
                $familyBilling->registerPhone($sanitizedPhone);
            }

            Student::query()
                ->where('family_code', $familyBilling->family_code)
                ->where(function ($query) {
                    $query->whereNull('parent_phone')->orWhere('parent_phone', '');
                })
                ->update([
                    'parent_phone' => $sanitizedPhone,
                ]);

            $studentIds = $familyStudents->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();
            if ($studentIds !== [] && $this->parentStudentLinksTableAvailable()) {
                $now = now();
                $existingIds = ParentStudentLink::query()
                    ->where('user_id', $parent->id)
                    ->whereIn('student_id', $studentIds)
                    ->pluck('student_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                $rows = collect($studentIds)
                    ->reject(fn (int $studentId): bool => in_array($studentId, $existingIds, true))
                    ->map(fn (int $studentId): array => [
                        'user_id' => $parent->id,
                        'student_id' => $studentId,
                        'relationship_type' => 'guardian',
                        'notes' => null,
                        'linked_by_user_id' => $actor?->id,
                        'linked_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                if ($rows !== []) {
                    ParentStudentLink::query()->insert($rows);
                }
            }
        });

        return $parent->fresh(['roles']);
    }

    /**
     * @return Collection<int, Student>
     */
    public function resolvedLinkedStudents(User $user): Collection
    {
        if ($this->parentStudentLinksTableAvailable()) {
            $explicitStudents = $user->linkedStudents()
                ->orderBy('class_name')
                ->orderBy('full_name')
                ->get();

            if ($explicitStudents->isNotEmpty()) {
                return $explicitStudents;
            }
        }

        $phone = ParentPhone::sanitizeInput((string) $user->phone);
        $email = mb_strtolower(trim((string) $user->email));

        if ($phone === '' && $email === '') {
            return collect();
        }

        $query = Student::query()
            ->where(function ($builder) use ($user): void {
                $matched = false;

                $phone = ParentPhone::sanitizeInput((string) $user->phone);
                if ($phone !== '') {
                    $matched = true;
                    $builder->whereIn('parent_phone', ParentPhone::variants($phone));
                }

                $email = mb_strtolower(trim((string) $user->email));
                if ($email !== '') {
                    $matched
                        ? $builder->orWhereRaw('LOWER(parent_email) = ?', [$email])
                        : $builder->whereRaw('LOWER(parent_email) = ?', [$email]);
                }
            })
            ->orderBy('class_name')
            ->orderBy('full_name');

        return $query->get();
    }

    /**
     * @return Collection<int, string>
     */
    public function accessibleFamilyCodesForUser(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        if ($this->parentStudentLinksTableAvailable()) {
            $explicitFamilyCodes = $user->linkedStudents()
                ->pluck('family_code')
                ->map(fn ($familyCode): string => trim((string) $familyCode))
                ->filter()
                ->unique()
                ->values();

            if ($explicitFamilyCodes->isNotEmpty()) {
                return $explicitFamilyCodes;
            }
        }

        $resolvedFamilyCodes = $this->resolvedLinkedStudents($user)
            ->pluck('family_code')
            ->map(fn ($familyCode): string => trim((string) $familyCode))
            ->filter();

        $phone = ParentPhone::sanitizeInput((string) $user->phone);
        $normalizedPhone = ParentPhone::normalizeForMatch($phone);

        $registeredFamilyCodes = $normalizedPhone === ''
            ? collect()
            : FamilyBilling::query()
                ->whereHas('phones', fn ($query) => $query->where('normalized_phone', $normalizedPhone))
                ->pluck('family_code');

        return $resolvedFamilyCodes
            ->merge($registeredFamilyCodes)
            ->map(fn ($familyCode): string => trim((string) $familyCode))
            ->filter()
            ->unique()
            ->values();
    }

    private function parentStudentLinksTableAvailable(): bool
    {
        return Schema::hasTable('parent_student_links');
    }
}
