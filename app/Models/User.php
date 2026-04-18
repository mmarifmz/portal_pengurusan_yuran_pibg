<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'class_name', 'is_payment_tester', 'is_active', 'receive_whatsapp_notifications'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
            'receive_whatsapp_notifications' => 'boolean',
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

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isSuperTeacher(): bool
    {
        return $this->role === 'super_teacher';
    }

    public function isSystemAdmin(): bool
    {
        return $this->role === 'system_admin';
    }

    public function isSystemInstaller(): bool
    {
        return $this->role === 'system_installer';
    }

    public function isParent(): bool
    {
        return $this->role === 'parent';
    }

    public function isPta(): bool
    {
        return $this->role === 'pta';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['teacher', 'super_teacher', 'system_admin', 'pta'], true);
    }

    public function canAccessTeacherRecords(): bool
    {
        return in_array($this->role, ['teacher', 'super_teacher', 'system_admin', 'pta'], true);
    }

    public function canManageTeacherUsers(): bool
    {
        return in_array($this->role, ['super_teacher', 'system_admin'], true);
    }

    public function isParentTester(): bool
    {
        return $this->isParent() && (bool) $this->is_payment_tester;
    }
}
