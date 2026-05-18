<?php

namespace App\Services;

use App\Models\TeacherRoleAssignmentAudit;
use App\Models\User;
use App\Support\MalaysianPhone;
use Illuminate\Support\Facades\DB;

class TeacherRoleAssignmentService
{
    /**
     * @return array{user:?User,matched_by:?string,normalized_phone:?string,conflict:?string}
     */
    public function matchExistingUser(?string $email, ?string $phone, ?int $ignoreUserId = null): array
    {
        $normalizedEmail = mb_strtolower(trim((string) $email));
        $normalizedPhone = MalaysianPhone::normalize($phone);

        $emailUser = $normalizedEmail !== ''
            ? User::query()
                ->when($ignoreUserId !== null, fn ($query) => $query->where('id', '!=', $ignoreUserId))
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->first()
            : null;

        $phoneUser = $normalizedPhone !== null
            ? User::query()
                ->when($ignoreUserId !== null, fn ($query) => $query->where('id', '!=', $ignoreUserId))
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->whereIn('phone', MalaysianPhone::variants($normalizedPhone))
                ->first()
            : null;

        if ($emailUser && $phoneUser && $emailUser->id !== $phoneUser->id) {
            return [
                'user' => null,
                'matched_by' => null,
                'normalized_phone' => $normalizedPhone,
                'conflict' => 'The provided email and WhatsApp number belong to different existing users.',
            ];
        }

        if ($emailUser) {
            return [
                'user' => $emailUser,
                'matched_by' => 'email',
                'normalized_phone' => $normalizedPhone,
                'conflict' => null,
            ];
        }

        if ($phoneUser) {
            return [
                'user' => $phoneUser,
                'matched_by' => 'phone',
                'normalized_phone' => $normalizedPhone,
                'conflict' => null,
            ];
        }

        return [
            'user' => null,
            'matched_by' => null,
            'normalized_phone' => $normalizedPhone,
            'conflict' => null,
        ];
    }

    /**
     * @param  array{name?:?string,email?:?string,phone?:?string,class_name?:?string,enable_whatsapp?:bool,name_update_mode?:string,matched_by?:?string}  $attributes
     * @return array{user:User,role_added:bool,converted_existing_user:bool,name_updated:bool,assigned_to_class:bool,whatsapp_enabled:bool,roles_before:array<int, string>,roles_after:array<int, string>}
     */
    public function assignTeacherRole(User $user, array $attributes, ?User $assignedBy, string $source): array
    {
        $rolesBefore = $user->roleNames();
        $roleAdded = ! $user->hasRole('teacher');
        $convertedExistingUser = $user->exists && $roleAdded && $rolesBefore !== [];
        $nameUpdated = false;
        $assignedToClass = false;
        $whatsappEnabled = false;

        $name = trim((string) ($attributes['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($attributes['email'] ?? '')));
        $phone = $attributes['phone'] ?? null;
        $className = filled($attributes['class_name'] ?? null) ? trim((string) $attributes['class_name']) : null;
        $nameUpdateMode = (string) ($attributes['name_update_mode'] ?? 'if_blank');

        DB::transaction(function () use (
            $user,
            $name,
            $email,
            $phone,
            $className,
            $assignedBy,
            $source,
            $nameUpdateMode,
            $attributes,
            &$roleAdded,
            &$nameUpdated,
            &$assignedToClass,
            &$whatsappEnabled,
            $rolesBefore
        ): void {
            if ($user->role === null || trim((string) $user->role) === '') {
                $user->role = 'teacher';
            }

            if ($name !== '' && (
                $nameUpdateMode === 'always'
                || ($nameUpdateMode === 'if_blank' && blank($user->getRawOriginal('name')))
            )) {
                $user->name = $name;
                $nameUpdated = true;
            }

            if ($email !== '' && (blank($user->email) || str_ends_with((string) $user->email, '@placeholder.local'))) {
                $user->email = $email;
            }

            if ($phone !== null) {
                $user->phone = $phone;
            }

            $user->is_active = true;

            if ($className !== null) {
                $user->class_name = $className;
                $assignedToClass = true;
            }

            if (blank($user->invite_status)) {
                $user->invite_status = 'pending';
            }

            if (($attributes['enable_whatsapp'] ?? false)
                && filled($user->phone)
                && filled($user->class_name)) {
                $user->receive_whatsapp_notifications = true;
                $whatsappEnabled = true;
            }

            $user->save();
            $user->assignRole('teacher');

            if (! $roleAdded) {
                $roleAdded = ! in_array('teacher', $rolesBefore, true) && $user->hasRole('teacher');
            }

            TeacherRoleAssignmentAudit::query()->create([
                'user_id' => $user->id,
                'previous_roles' => $rolesBefore,
                'new_role' => 'teacher',
                'class_name' => $className,
                'assigned_by' => $assignedBy?->id,
                'assigned_at' => now(),
                'source' => $source,
                'meta' => [
                    'matched_by' => $attributes['matched_by'] ?? null,
                    'name_updated' => $nameUpdated,
                    'role_added' => $roleAdded,
                    'roles_after' => $user->fresh()->roleNames(),
                ],
            ]);
        });

        $user->refresh()->loadMissing('roles');

        return [
            'user' => $user,
            'role_added' => $roleAdded,
            'converted_existing_user' => $convertedExistingUser,
            'name_updated' => $nameUpdated,
            'assigned_to_class' => $assignedToClass,
            'whatsapp_enabled' => $whatsappEnabled,
            'roles_before' => $rolesBefore,
            'roles_after' => $user->roleNames(),
        ];
    }
}
