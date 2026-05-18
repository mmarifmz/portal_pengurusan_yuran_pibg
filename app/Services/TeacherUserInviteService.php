<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class TeacherUserInviteService
{
    public function __construct(private readonly WhatsAppTacSender $whatsAppTacSender)
    {
    }

    /**
     * @return array{
     *   status:string,
     *   dashboard_url:string,
     *   reset_url:string,
     *   message:string,
     *   error:?string
     * }
     */
    public function send(User $teacher): array
    {
        $usesExistingAccount = $teacher->hasMultipleRoles() || $teacher->role !== 'teacher';
        $dashboardUrl = route($usesExistingAccount ? 'dashboard' : 'teacher.dashboard', absolute: true);
        $resetUrl = route('password.reset', [
            'token' => Password::broker()->createToken($teacher),
            'email' => $teacher->email,
        ], absolute: true);

        $message = $this->buildMessage($teacher, $dashboardUrl, $resetUrl, $usesExistingAccount);

        if (! $teacher->isTeacher()) {
            return [
                'status' => 'failed',
                'dashboard_url' => $dashboardUrl,
                'reset_url' => $resetUrl,
                'message' => $message,
                'error' => 'Only teacher accounts can receive teacher dashboard invites.',
            ];
        }

        if (! $teacher->is_active) {
            return [
                'status' => 'failed',
                'dashboard_url' => $dashboardUrl,
                'reset_url' => $resetUrl,
                'message' => $message,
                'error' => 'Inactive teacher accounts cannot receive invites.',
            ];
        }

        if (blank($teacher->phone)) {
            return [
                'status' => 'failed',
                'dashboard_url' => $dashboardUrl,
                'reset_url' => $resetUrl,
                'message' => $message,
                'error' => 'A valid WhatsApp number is required before sending an invite.',
            ];
        }

        if (! (bool) config('services.whatsapp.enabled')) {
            $teacher->forceFill([
                'teacher_invite_sent_at' => now(),
                'invite_status' => 'manual',
            ])->save();

            Log::notice('Teacher invite requires manual sending.', [
                'teacher_user_id' => $teacher->id,
                'phone' => $teacher->phone,
                'email' => $teacher->email,
            ]);

            return [
                'status' => 'manual',
                'dashboard_url' => $dashboardUrl,
                'reset_url' => $resetUrl,
                'message' => $message,
                'error' => null,
            ];
        }

        try {
            $this->whatsAppTacSender->sendMessage((string) $teacher->phone, $message);

            $teacher->forceFill([
                'teacher_invite_sent_at' => now(),
                'invite_status' => 'sent',
            ])->save();

            return [
                'status' => 'sent',
                'dashboard_url' => $dashboardUrl,
                'reset_url' => $resetUrl,
                'message' => $message,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            $teacher->forceFill([
                'invite_status' => 'failed',
            ])->save();

            Log::warning('Teacher invite send failed.', [
                'teacher_user_id' => $teacher->id,
                'phone' => $teacher->phone,
                'email' => $teacher->email,
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'dashboard_url' => $dashboardUrl,
                'reset_url' => $resetUrl,
                'message' => $message,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function buildMessage(User $teacher, string $dashboardUrl, string $resetUrl, bool $usesExistingAccount): string
    {
        $className = filled($teacher->class_name) ? (string) $teacher->class_name : 'Belum ditetapkan';

        if ($usesExistingAccount) {
            return implode("\n\n", [
                "Assalamualaikum / Salam Sejahtera {$teacher->name},",
                'Akses Guru Kelas untuk Portal Yuran PIBG SK Sri Petaling telah diaktifkan pada akaun sedia ada cikgu.',
                "Kelas: {$className}",
                "Sila login menggunakan akaun sedia ada melalui pautan berikut:\n{$dashboardUrl}",
                'Selepas login, cikgu boleh pilih Teacher Dashboard untuk melihat status bayaran kelas.',
                'Terima kasih.',
            ]);
        }

        return implode("\n\n", [
            "Assalamualaikum / Salam Sejahtera {$teacher->name},",
            'Akaun Guru Kelas untuk Portal Yuran PIBG SK Sri Petaling telah diwujudkan.',
            "Kelas: {$className}",
            "Sila login melalui pautan berikut:\n{$dashboardUrl}",
            "Emel: {$teacher->email}",
            "Tetapkan kata laluan melalui pautan berikut:\n{$resetUrl}",
            'Mohon tukar kata laluan selepas login pertama.',
            'Terima kasih.',
        ]);
    }
}
