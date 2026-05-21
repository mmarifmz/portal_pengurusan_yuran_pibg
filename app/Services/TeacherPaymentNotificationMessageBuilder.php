<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;

class TeacherPaymentNotificationMessageBuilder
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function build(array $context): string
    {
        $teacherName = trim((string) ($context['teacher_name'] ?? 'Cikgu'));
        $studentNames = collect($context['student_names'] ?? [])
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->values()
            ->all();
        $studentText = $studentNames === [] ? '-' : implode(', ', $studentNames);

        $lines = [
            "Assalamualaikum dan Selamat Sejahtera {$teacherName} 🌸",
            '',
            $this->introLine($context),
            '',
            '━━━━━━━━━━━━━━━',
            '📌 MAKLUMAT BAYARAN',
            '━━━━━━━━━━━━━━━',
            '',
            '👨‍👩‍👧 Kod Keluarga:',
            (string) ($context['family_code'] ?? '-'),
            '',
            '📅 Tahun Bil:',
            (string) ($context['bill_year'] ?? '-'),
            '',
            '🏫 Kelas:',
            (string) ($context['class_name'] ?? '-'),
            '',
            '👦 Murid:',
            $studentText,
            '',
            '🧾 Order ID:',
            (string) ($context['order_id'] ?? '-'),
            '',
            '━━━━━━━━━━━━━━━',
            '💳 PECAHAN BAYARAN',
            '━━━━━━━━━━━━━━━',
            '',
            '• Yuran PIBG:',
            'RM'.$this->money($context['pibg_amount'] ?? 0),
        ];

        if ((float) ($context['donation_amount'] ?? 0) > 0) {
            $lines[] = '';
            $lines[] = '• Sumbangan Tambahan:';
            $lines[] = 'RM'.$this->money($context['donation_amount'] ?? 0);
        }

        $lines[] = '';
        $lines[] = '• Jumlah Keseluruhan:';
        $lines[] = 'RM'.$this->money($context['total_amount'] ?? 0);

        if (($context['is_instalment'] ?? false) && ! ($context['is_payment_complete'] ?? true)) {
            $lines[] = '';
            $lines[] = '━━━━━━━━━━━━━━━';
            $lines[] = 'ℹ️ STATUS BAYARAN';
            $lines[] = '━━━━━━━━━━━━━━━';
            $lines[] = '';
            $lines[] = 'Bayaran ansuran telah diterima.';
            $lines[] = 'Status keluarga masih belum lengkap sehingga semua bayaran selesai.';
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━';
        $lines[] = '🌐 RESIT BAYARAN';
        $lines[] = '━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = (string) ($context['receipt_url'] ?? '-');
        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━';
        $lines[] = '📊 DASHBOARD GURU';
        $lines[] = '━━━━━━━━━━━━━━━';
        $lines[] = '';
        $lines[] = 'Semakan status kutipan kelas:';
        $lines[] = $this->teacherDashboardUrl();
        $lines[] = '';
        $lines[] = 'Terima kasih atas kerjasama dan sokongan cikgu terhadap pengurusan PIBG SK Sri Petaling 🤝';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function introLine(array $context): string
    {
        if (($context['is_instalment'] ?? false) && ! ($context['is_payment_complete'] ?? true)) {
            return 'Makluman bahawa bayaran ansuran PIBG bagi murid di bawah kelas jagaan cikgu telah diterima.';
        }

        return 'Makluman bahawa bayaran PIBG bagi murid di bawah kelas jagaan cikgu telah berjaya diterima.';
    }

    private function teacherDashboardUrl(): string
    {
        if (Route::has('teacher.dashboard')) {
            return route('teacher.dashboard');
        }

        return rtrim((string) config('app.url'), '/').'/teacher';
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2);
    }
}
