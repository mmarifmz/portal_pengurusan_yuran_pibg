<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PaymentReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TeacherClassProgressController extends Controller
{
    public function __construct(
        private readonly PaymentReportingService $paymentReportingService
    ) {
    }

    public function index(Request $request): View
    {
        $billingYear = (int) now()->year;
        $leaderboardRows = $this->paymentReportingService->classLeaderboard($billingYear);

        $classNames = $leaderboardRows
            ->pluck('class_name')
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->values();

        $teacherByClass = User::query()
            ->whereIn('role', ['teacher', 'super_teacher'])
            ->whereIn('class_name', $classNames->all())
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderByRaw("case when role = 'teacher' then 0 else 1 end")
            ->orderBy('name')
            ->get(['name', 'phone', 'class_name'])
            ->groupBy(fn (User $teacher): string => trim((string) $teacher->class_name))
            ->map(fn (Collection $rows): ?User => $rows->first());

        $leaderboardRows = $leaderboardRows
            ->map(function (array $row) use ($teacherByClass, $billingYear): array {
                /** @var User|null $teacher */
                $teacher = $teacherByClass->get((string) $row['class_name']);
                $teacherPhone = $teacher ? trim((string) $teacher->phone) : '';

                $row['year_level'] = $this->extractYearLevel((string) $row['class_name']);
                $row['teacher_name'] = $teacher ? (string) $teacher->name : '-';
                $row['teacher_phone'] = $teacherPhone !== '' ? $teacherPhone : '-';
                $row['teacher_whatsapp_url'] = $teacherPhone !== ''
                    ? 'https://wa.me/'.$this->normalizeWaPhone($teacherPhone)
                        .'?text='.rawurlencode($this->buildTeacherSummaryMessage(
                            (string) $teacher->name,
                            (string) $row['class_name'],
                            $billingYear,
                            (int) $row['fully_paid_families'],
                            (int) $row['partial_paid_families'],
                            (int) $row['unpaid_families'],
                            (int) $row['total_families'],
                            (float) $row['yuran_collected'],
                            (float) $row['baki_tertunggak']
                        ))
                    : null;

                return $row;
            })
            ->values();

        $yearLevelOptions = $leaderboardRows
            ->pluck('year_level')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('teacher.class-progress', [
            'billingYear' => $billingYear,
            'leaderboardRows' => $leaderboardRows,
            'yearLevelOptions' => $yearLevelOptions,
        ]);
    }

    private function extractYearLevel(string $className): ?int
    {
        if (preg_match('/^(\d+)/', trim($className), $matches) !== 1) {
            return null;
        }

        $year = (int) ($matches[1] ?? 0);

        return ($year >= 1 && $year <= 6) ? $year : null;
    }

    private function normalizeWaPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '60';
        }

        if (str_starts_with($digits, '60')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '6'.$digits;
        }

        if (str_starts_with($digits, '1')) {
            return '60'.$digits;
        }

        return $digits;
    }

    private function buildTeacherSummaryMessage(
        string $teacherName,
        string $className,
        int $billingYear,
        int $fullyPaid,
        int $partialPaid,
        int $unpaid,
        int $totalFamilies,
        float $yuranCollected,
        float $outstanding
    ): string {
        $name = trim($teacherName) !== '' ? trim($teacherName) : 'Cikgu';

        return implode("\n", [
            'Assalamualaikum '.$name.',',
            '',
            'Ringkasan pembayaran kelas '.$className.' ('.$billingYear.')',
            'Jumlah keluarga: '.$totalFamilies,
            'Selesai bayar: '.$fullyPaid,
            'Bayaran sebahagian: '.$partialPaid,
            'Belum bayar: '.$unpaid,
            'Kutipan yuran: RM'.number_format($yuranCollected, 2),
            'Baki tertunggak: RM'.number_format($outstanding, 2),
        ]);
    }
}
