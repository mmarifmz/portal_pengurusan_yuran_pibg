<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Collection;

class MessageFormatterService
{
    public function __construct(private readonly int $safeMessageLength = 1500)
    {
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $paidStudents
     * @return array<int, array<string, mixed>>
     */
    public function buildSummaryMessage(
        string $className,
        string $teacherName,
        int $totalStudents,
        int $paidCount,
        int $unpaidCount,
        float $paymentPercentage,
        float $pibgAmount,
        float $additionalDonation,
        float $totalCollected,
        float $expectedAmount,
        int $rank,
        Collection $paidStudents
    ): array {
        $lines = [
            '📊 *Ringkasan Yuran & Sumbangan PIBG*',
            '',
            "Assalamualaikum / Salam Sejahtera *{$teacherName}*,",
            '',
            "Berikut adalah status kutipan semasa bagi kelas *{$className}* 🏫",
            '',
            "👥 *Jumlah Murid:* {$totalStudents}",
            "✅ *Telah Bayar:* {$paidCount}",
            "⏳ *Belum Bayar:* {$unpaidCount}",
            '📈 *Peratus Bayaran:* *'.number_format($paymentPercentage, 2).'%*',
            '',
            '💰 *Yuran PIBG:* '.$this->formatCurrency($pibgAmount),
            '🎁 *Sumbangan Tambahan:* '.$this->formatCurrency($additionalDonation),
            '🧾 *Jumlah Kutipan:* *'.$this->formatCurrency($totalCollected).'*',
            '🎯 *Sasaran Kutipan:* '.$this->formatCurrency($expectedAmount),
            "🏆 *Ranking Semasa:* #{$rank}",
        ];

        if ($paidStudents->isEmpty()) {
            $lines[] = '';
            $lines[] = '📭 _Belum ada rekod bayaran diterima untuk kelas ini setakat ini._';
        }

        $lines[] = '';
        $lines[] = '🙏 Terima kasih atas bantuan cikgu untuk mengingatkan ibu bapa yang masih belum membuat bayaran.';

        return $this->chunkMessage('summary', '📊 *Ringkasan Yuran & Sumbangan PIBG*', $lines);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $paidStudents
     * @return array<int, array<string, mixed>>
     */
    public function buildPaidListMessage(string $className, Collection $paidStudents): array
    {
        $heading = "✅ *{$className} - Senarai Telah Bayar*";

        if ($paidStudents->isEmpty()) {
            return $this->chunkMessage('paid_list', $heading, [
                'Tiada rekod bayaran setakat ini.',
            ]);
        }

        $lines = [];

        foreach ($paidStudents->values() as $index => $student) {
            if ($index > 0) {
                $lines[] = '';
            }

            $lines[] = sprintf('%d. %s', $index + 1, (string) $student['student_name']);
        }

        return $this->chunkMessage('paid_list', $heading, $lines);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $unpaidStudents
     * @return array<int, array<string, mixed>>
     */
    public function buildUnpaidListMessage(string $className, Collection $unpaidStudents): array
    {
        $heading = "⏳ *{$className} - Senarai Belum Bayar*";

        $lines = $unpaidStudents->isEmpty()
            ? ['🎉 Semua murid telah membuat bayaran. Terima kasih cikgu.']
            : $unpaidStudents
                ->values()
                ->map(fn (array $student, int $index): string => sprintf(
                    '%d. %s',
                    $index + 1,
                    (string) $student['student_name']
                ))
                ->all();

        return $this->chunkMessage('unpaid_list', $heading, $lines);
    }

    private function formatCurrency(float $amount): string
    {
        return 'RM '.number_format($amount, 2);
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function chunkMessage(string $messagePart, string $heading, array $lines): array
    {
        if (($lines[0] ?? null) !== $heading) {
            $lines = [$heading, '', ...$lines];
        }

        $segments = [];
        $current = [];

        foreach ($lines as $line) {
            $candidateLines = [...$current, $line];
            $candidate = implode("\n", $candidateLines);

            if (mb_strlen($candidate) > $this->safeMessageLength && $current !== []) {
                $segments[] = $this->trimTrailingEmptyLines($current);
                $current = [$heading, '', $line];

                continue;
            }

            $current = $candidateLines;
        }

        if ($current !== []) {
            $segments[] = $this->trimTrailingEmptyLines($current);
        }

        $segmentCount = count($segments);

        return collect($segments)
            ->map(function (array $segmentLines, int $index) use ($messagePart, $segmentCount, $heading): array {
                $body = implode("\n", $segmentLines);

                if ($segmentCount > 1) {
                    $segmentHeading = sprintf('%s (%d/%d)', $heading, $index + 1, $segmentCount);
                    $body = preg_replace('/^'.preg_quote($heading, '/').'/u', $segmentHeading, $body, 1) ?? $body;
                }

                return [
                    'message_part' => $messagePart,
                    'part_label' => $heading,
                    'segment' => $index + 1,
                    'segment_count' => $segmentCount,
                    'body' => $body,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function trimTrailingEmptyLines(array $lines): array
    {
        while ($lines !== [] && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        return $lines;
    }
}
