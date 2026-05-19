<?php

namespace App\Services;

use App\Models\User;
use App\Support\MalaysianPhone;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TeacherOnboardingInviteService
{
    public function defaultTemporaryPassword(): string
    {
        $password = trim((string) config('teacher.default_password', ''));

        if ($password === '') {
            throw new InvalidArgumentException('Set TEACHER_DEFAULT_PASSWORD before generating teacher onboarding invites.');
        }

        return $password;
    }

    public function defaultDashboardUrl(): string
    {
        return route('teacher.dashboard', absolute: true);
    }

    /**
     * @param  Collection<int, User>  $teachers
     * @return array<int, array<string, mixed>>
     */
    public function generateForTeachers(Collection $teachers, array $options, ?User $generatedBy = null): array
    {
        $temporaryPassword = $this->resolveTemporaryPassword($options['temporary_password'] ?? null);
        $dashboardUrl = $this->resolveDashboardUrl($options['dashboard_url'] ?? null);
        $resetPasswords = (bool) ($options['reset_passwords'] ?? false);

        return $teachers
            ->map(fn (User $teacher): array => $this->generateForTeacher($teacher, $temporaryPassword, $dashboardUrl, $resetPasswords, $generatedBy))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function generateForTeacher(User $teacher, string $temporaryPassword, string $dashboardUrl, bool $resetPassword = false, ?User $generatedBy = null): array
    {
        if (! $teacher->hasRole('teacher')) {
            throw new InvalidArgumentException('Only teacher accounts can receive onboarding invites.');
        }

        if (! $teacher->is_active) {
            throw new InvalidArgumentException('Inactive teacher accounts cannot receive onboarding invites.');
        }

        $normalizedPhone = MalaysianPhone::normalize($teacher->phone);
        if ($normalizedPhone === null) {
            throw new InvalidArgumentException('A valid WhatsApp number is required before generating an onboarding invite.');
        }

        if ($resetPassword) {
            $teacher->password = $temporaryPassword;
        }

        $updates = [
            'invite_status' => 'manual',
        ];

        if (User::onboardingInviteColumnsAvailable()) {
            $updates['onboarding_invite_generated_at'] = now();
            $updates['onboarding_invite_method'] = 'whatsapp_web';
            $updates['onboarding_invite_status'] = 'generated';
        }

        $teacher->forceFill($updates)->save();

        return $this->buildInvitePayload($teacher->fresh(), $temporaryPassword, $dashboardUrl, $resetPassword, [
            'status' => 'generated',
            'status_label' => 'Generated',
            'generated_at' => optional($teacher->fresh()->onboarding_invite_generated_at)?->toIso8601String(),
            'generated_at_display' => optional($teacher->fresh()->onboarding_invite_generated_at)?->format('d M Y H:i'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function buildInvitePayload(User $teacher, string $temporaryPassword, string $dashboardUrl, bool $resetPassword = false, array $overrides = []): array
    {
        if (! $teacher->hasRole('teacher')) {
            throw new InvalidArgumentException('Only teacher accounts can receive onboarding invites.');
        }

        $normalizedPhone = MalaysianPhone::normalize($teacher->phone);
        if ($normalizedPhone === null) {
            throw new InvalidArgumentException('A valid WhatsApp number is required before generating an onboarding invite.');
        }

        $message = $this->buildMessage($teacher, $temporaryPassword, $dashboardUrl, $resetPassword);
        $usesExistingAccount = $this->usesExistingAccount($teacher, $resetPassword);

        return array_merge([
            'teacher_id' => $teacher->id,
            'name' => (string) $teacher->name,
            'email' => (string) $teacher->email,
            'class_name' => filled($teacher->class_name) ? (string) $teacher->class_name : 'Belum ditetapkan',
            'phone' => (string) $normalizedPhone,
            'wa_phone' => ltrim($normalizedPhone, '+'),
            'message' => $message,
            'wa_link' => $this->buildWhatsappUrl($normalizedPhone, $message),
            'status' => (string) ($teacher->onboarding_invite_status ?: 'not_generated'),
            'status_label' => $teacher->onboarding_invite_status === 'sent_manual'
                ? 'Sent Manually'
                : ($teacher->onboarding_invite_status === 'generated' ? 'Generated' : 'Not Generated'),
            'generated_at' => optional($teacher->onboarding_invite_generated_at)?->toIso8601String(),
            'generated_at_display' => optional($teacher->onboarding_invite_generated_at)?->format('d M Y H:i'),
            'sent_at' => optional($teacher->onboarding_invite_sent_manually_at)?->toIso8601String(),
            'sent_at_display' => optional($teacher->onboarding_invite_sent_manually_at)?->format('d M Y H:i'),
            'uses_existing_account' => $usesExistingAccount,
            'dashboard_url' => $dashboardUrl,
            'temporary_password' => $temporaryPassword,
        ], $overrides);
    }

    public function buildPreview(?User $teacher, ?string $temporaryPassword = null, ?string $dashboardUrl = null): string
    {
        if ($teacher === null) {
            return "Pratonton mesej akan dipaparkan di sini selepas sekurang-kurangnya seorang guru yang layak ditemui.";
        }

        return $this->buildMessage(
            $teacher,
            $this->resolveTemporaryPassword($temporaryPassword),
            $this->resolveDashboardUrl($dashboardUrl),
            false,
        );
    }

    public function markSent(User $teacher, User $sentBy): User
    {
        if (! $teacher->hasRole('teacher')) {
            throw new InvalidArgumentException('Only teacher accounts can be marked as invited.');
        }

        $teacher->forceFill([
            'teacher_invite_sent_at' => now(),
            'invite_status' => 'manual',
        ] + (User::onboardingInviteColumnsAvailable() ? [
            'onboarding_invite_sent_manually_at' => now(),
            'onboarding_invite_sent_by' => $sentBy->id,
            'onboarding_invite_method' => 'whatsapp_web',
            'onboarding_invite_status' => 'sent_manual',
        ] : []))->save();

        return $teacher->fresh();
    }

    public function resolveTemporaryPassword(?string $value = null): string
    {
        $password = trim((string) ($value ?? $this->defaultTemporaryPassword()));

        if ($password === '') {
            throw new InvalidArgumentException('A temporary password is required before generating onboarding invites.');
        }

        return $password;
    }

    public function resolveDashboardUrl(?string $value = null): string
    {
        $dashboardUrl = trim((string) ($value ?? $this->defaultDashboardUrl()));

        if ($dashboardUrl === '' || filter_var($dashboardUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('A valid teacher dashboard URL is required before generating onboarding invites.');
        }

        return $dashboardUrl;
    }

    public function buildWhatsappUrl(string $normalizedPhone, string $message): string
    {
        return 'https://wa.me/'.ltrim($normalizedPhone, '+').'?text='.rawurlencode($message);
    }

    private function usesExistingAccount(User $teacher, bool $resetPassword): bool
    {
        if ($resetPassword) {
            return false;
        }

        return $teacher->hasMultipleRoles() || $teacher->role !== 'teacher';
    }

    public function buildMessage(User $teacher, string $temporaryPassword, string $dashboardUrl, bool $resetPassword = false): string
    {
        $usesExistingAccount = $this->usesExistingAccount($teacher, $resetPassword);
        $className = filled($teacher->class_name) ? (string) $teacher->class_name : 'Belum ditetapkan';

        $lines = [
            "Assalamualaikum / Salam Sejahtera *{$teacher->name}*,",
            'Akaun *Guru Kelas* untuk *Portal Yuran PIBG SK Sri Petaling* telah disediakan.',
            "🏫 *Kelas:* {$className}",
            "🔗 *Pautan Login:*\n{$dashboardUrl}",
            "📧 *Emel Login:*\n{$teacher->email}",
        ];

        if ($usesExistingAccount) {
            $lines[] = '🔐 *Akses Akaun:*
Gunakan login sedia ada cikgu untuk masuk ke sistem.';
        } else {
            $lines[] = "🔐 *Kata Laluan Sementara:*\n{$temporaryPassword}";
        }

        $lines[] = 'Selepas login, cikgu boleh menggunakan fungsi berikut:';
        $lines[] = "📊 *Class Progress*\nMelihat ringkasan bayaran kelas, jumlah telah bayar, belum bayar, bayaran sebahagian, sumbangan tambahan dan baki tertunggak.";
        $lines[] = "✅ *Senarai Telah Bayar*\nMelihat senarai murid/keluarga yang telah membuat bayaran serta jumlah bayaran.";
        $lines[] = "⏳ *Senarai Belum Bayar*\nMelihat senarai murid/keluarga yang belum membuat bayaran.";
        $lines[] = "🔵 *Pill Tahun Lepas*\nJika terdapat tanda kecil seperti *25*, ini bermaksud keluarga tersebut pernah membuat bayaran pada tahun 2025 tetapi belum membayar untuk sesi semasa.";
        $lines[] = "📂 *Kelas Lain*\nCikgu juga boleh membuka kelas lain untuk semakan ringkas status bayaran.";
        $lines[] = 'Mohon cikgu login dan tukar kata laluan selepas login pertama jika diminta oleh sistem.';
        $lines[] = 'Terima kasih atas bantuan cikgu.';

        return implode("\n\n", $lines);
    }
}
