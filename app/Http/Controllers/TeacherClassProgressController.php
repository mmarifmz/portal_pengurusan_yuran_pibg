<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TeacherClassProgressController extends Controller
{
    public function index(Request $request): View
    {
        $currentYear = (int) now()->year;
        $billingYear = $currentYear;

        $students = Student::query()
            ->where('billing_year', $billingYear)
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get(['family_code', 'full_name', 'class_name']);

        $familyCodes = $students
            ->pluck('family_code')
            ->map(fn ($familyCode): string => trim((string) $familyCode))
            ->filter()
            ->unique()
            ->values();

        $paidFamilyMap = FamilyBilling::query()
            ->where('billing_year', $billingYear)
            ->whereIn('family_code', $familyCodes->all())
            ->get(['family_code', 'status', 'fee_amount', 'paid_amount'])
            ->mapWithKeys(function (FamilyBilling $billing): array {
                $feeAmount = (float) $billing->fee_amount;
                $paidAmount = (float) $billing->paid_amount;
                $isPaid = $billing->status === 'paid' || ($feeAmount > 0 && $paidAmount >= $feeAmount);

                return [(string) $billing->family_code => $isPaid];
            });

        $classNames = $students
            ->pluck('class_name')
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->unique()
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

        $progressByClass = $students
            ->groupBy(fn (Student $student): string => trim((string) $student->class_name))
            ->map(function (Collection $classStudents, string $className) use ($paidFamilyMap, $teacherByClass, $billingYear): array {
                $total = $classStudents->count();

                $paidStudents = $classStudents
                    ->filter(fn (Student $student): bool => (bool) $paidFamilyMap->get(trim((string) $student->family_code), false))
                    ->pluck('full_name')
                    ->map(fn ($name): string => trim((string) $name))
                    ->filter()
                    ->values();

                $unpaidStudents = $classStudents
                    ->reject(fn (Student $student): bool => (bool) $paidFamilyMap->get(trim((string) $student->family_code), false))
                    ->pluck('full_name')
                    ->map(fn ($name): string => trim((string) $name))
                    ->filter()
                    ->values();

                $paidCount = $paidStudents->count();
                $unpaidCount = $unpaidStudents->count();
                $progressPercent = $total > 0 ? round(($paidCount / $total) * 100, 1) : 0;

                /** @var User|null $teacher */
                $teacher = $teacherByClass->get($className);
                $teacherPhone = $teacher ? trim((string) $teacher->phone) : '';

                $whatsAppUrl = null;
                if ($teacherPhone !== '') {
                    $whatsAppUrl = 'https://wa.me/'.$this->normalizeWaPhone($teacherPhone)
                        .'?text='.rawurlencode($this->buildTeacherSummaryMessage(
                            (string) $teacher->name,
                            $className,
                            $billingYear,
                            $paidCount,
                            $unpaidCount,
                            $total,
                            $unpaidStudents
                        ));
                }

                return [
                    'class_name' => $className,
                    'year_level' => $this->extractYearLevel($className),
                    'total_students' => $total,
                    'paid_count' => $paidCount,
                    'unpaid_count' => $unpaidCount,
                    'progress_percent' => $progressPercent,
                    'paid_students' => $paidStudents->all(),
                    'unpaid_students' => $unpaidStudents->all(),
                    'teacher_name' => $teacher ? (string) $teacher->name : '-',
                    'teacher_phone' => $teacherPhone !== '' ? $teacherPhone : '-',
                    'teacher_whatsapp_url' => $whatsAppUrl,
                ];
            })
            ->sortBy('class_name')
            ->values();

        $yearLevelOptions = $progressByClass
            ->pluck('year_level')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('teacher.class-progress', [
            'billingYear' => $billingYear,
            'progressByClass' => $progressByClass,
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
        int $paidCount,
        int $unpaidCount,
        int $total,
        Collection $unpaidStudents
    ): string {
        $name = trim($teacherName) !== '' ? trim($teacherName) : 'Cikgu';

        $lines = [
            'Assalamualaikum '.$name.',',
            '',
            'Ringkasan semakan yuran kelas '.$className.' ('.$billingYear.')',
            'Murid telah menjelaskan Yuran: '.$paidCount.' daripada '.$total,
            'Murid belum menjelaskan Yuran: '.$unpaidCount.' daripada '.$total,
        ];

        if ($unpaidCount > 0) {
            $lines[] = '';
            $lines[] = 'Senarai belum jelas (maks 20):';

            foreach ($unpaidStudents->take(20) as $index => $studentName) {
                $lines[] = ($index + 1).'. '.trim((string) $studentName);
            }
        }

        return implode("\n", $lines);
    }
}