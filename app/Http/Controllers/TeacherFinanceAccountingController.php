<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherFinanceAccountingController extends Controller
{
    public function index(Request $request): View
    {
        $dataset = $this->buildDataset($request);

        return view('teacher.finance-accounting', [
            'rows' => $dataset['rows'],
            'totals' => $dataset['totals'],
            'search' => $dataset['search'],
            'classFilter' => $dataset['classFilter'],
            'classOptions' => $dataset['classOptions'],
            'yearA' => $dataset['yearA'],
            'yearB' => $dataset['yearB'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $dataset = $this->buildDataset($request);
        $rows = $dataset['rows'];
        $totals = $dataset['totals'];
        $yearA = $dataset['yearA'];
        $yearB = $dataset['yearB'];

        $fileName = sprintf('finance-accounting-%d-%d.csv', $yearA, $yearB);

        return response()->streamDownload(function () use ($rows, $totals, $yearA, $yearB): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Family Code',
                'Name',
                'Class Name',
                "Yuran {$yearA}",
                "Sumbangan {$yearA}",
                "Yuran {$yearB}",
                "Sumbangan {$yearB}",
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['family_code'],
                    $row['name'],
                    $row['class_name'],
                    number_format((float) $row["yuran_{$yearA}"], 2, '.', ''),
                    number_format((float) $row["sumbangan_{$yearA}"], 2, '.', ''),
                    number_format((float) $row["yuran_{$yearB}"], 2, '.', ''),
                    number_format((float) $row["sumbangan_{$yearB}"], 2, '.', ''),
                ]);
            }

            fputcsv($handle, [
                'TOTAL',
                '',
                '',
                number_format((float) $totals["yuran_{$yearA}"], 2, '.', ''),
                number_format((float) $totals["sumbangan_{$yearA}"], 2, '.', ''),
                number_format((float) $totals["yuran_{$yearB}"], 2, '.', ''),
                number_format((float) $totals["sumbangan_{$yearB}"], 2, '.', ''),
            ]);

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{
     *   rows: \Illuminate\Support\Collection<int, array<string, float|string>>,
     *   totals: array<string, float>,
     *   search: string,
     *   classFilter: string,
     *   classOptions: \Illuminate\Support\Collection<int, string>,
     *   yearA: int,
     *   yearB: int
     * }
     */
    private function buildDataset(Request $request): array
    {
        $yearA = (int) $request->integer('year_a', 2025);
        $yearB = (int) $request->integer('year_b', 2026);
        $search = trim((string) $request->query('search', ''));
        $classFilter = trim((string) $request->query('class_name', ''));

        if ($yearA < 2000 || $yearA > 2100) {
            $yearA = 2025;
        }

        if ($yearB < 2000 || $yearB > 2100) {
            $yearB = 2026;
        }

        $studentsByFamily = Student::query()
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->orderBy('family_code')
            ->orderBy('full_name')
            ->get()
            ->groupBy(fn (Student $student): string => (string) $student->family_code);

        $billingByYearAndFamily = FamilyBilling::query()
            ->whereIn('billing_year', [$yearA, $yearB])
            ->get()
            ->groupBy('billing_year')
            ->map(fn (Collection $yearRows): Collection => $yearRows->keyBy('family_code'));

        $classOptions = $studentsByFamily
            ->map(fn (Collection $familyStudents): string => $this->resolveClassName($familyStudents))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $rows = $studentsByFamily
            ->map(function (Collection $familyStudents, string $familyCode) use ($billingByYearAndFamily, $yearA, $yearB): array {
                $billingA = $billingByYearAndFamily->get($yearA)?->get($familyCode);
                $billingB = $billingByYearAndFamily->get($yearB)?->get($familyCode);

                [$yuranA, $sumbanganA] = $this->splitYuranAndSumbangan(
                    (float) ($billingA?->paid_amount ?? 0),
                    (float) ($billingA?->fee_amount ?? 0)
                );
                [$yuranB, $sumbanganB] = $this->splitYuranAndSumbangan(
                    (float) ($billingB?->paid_amount ?? 0),
                    (float) ($billingB?->fee_amount ?? 0)
                );

                return [
                    'family_code' => $familyCode,
                    'name' => $this->resolveDisplayName($familyStudents),
                    'class_name' => $this->resolveClassName($familyStudents),
                    "yuran_{$yearA}" => $yuranA,
                    "sumbangan_{$yearA}" => $sumbanganA,
                    "yuran_{$yearB}" => $yuranB,
                    "sumbangan_{$yearB}" => $sumbanganB,
                ];
            })
            ->values();

        if ($classFilter !== '') {
            $rows = $rows
                ->filter(fn (array $row): bool => (string) $row['class_name'] === $classFilter)
                ->values();
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows
                ->filter(function (array $row) use ($needle): bool {
                    return str_contains(mb_strtolower((string) $row['family_code']), $needle)
                        || str_contains(mb_strtolower((string) $row['name']), $needle)
                        || str_contains(mb_strtolower((string) $row['class_name']), $needle);
                })
                ->values();
        }

        $totals = [
            "yuran_{$yearA}" => (float) $rows->sum("yuran_{$yearA}"),
            "sumbangan_{$yearA}" => (float) $rows->sum("sumbangan_{$yearA}"),
            "yuran_{$yearB}" => (float) $rows->sum("yuran_{$yearB}"),
            "sumbangan_{$yearB}" => (float) $rows->sum("sumbangan_{$yearB}"),
        ];

        return [
            'rows' => $rows,
            'totals' => $totals,
            'search' => $search,
            'classFilter' => $classFilter,
            'classOptions' => $classOptions,
            'yearA' => $yearA,
            'yearB' => $yearB,
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function splitYuranAndSumbangan(float $paidAmount, float $feeAmount): array
    {
        $safePaid = max(0, $paidAmount);
        $safeFee = max(0, $feeAmount);
        $yuran = min($safePaid, $safeFee);
        $sumbangan = max(0, $safePaid - $safeFee);

        return [round($yuran, 2), round($sumbangan, 2)];
    }

    private function resolveDisplayName(Collection $familyStudents): string
    {
        $parentName = $familyStudents
            ->pluck('parent_name')
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '' && preg_match('/^parent\s+ssp-/i', $value) !== 1)
            ->first();

        if (filled($parentName)) {
            return (string) $parentName;
        }

        $studentName = $familyStudents
            ->pluck('full_name')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->first();

        return $studentName ?: '-';
    }

    private function resolveClassName(Collection $familyStudents): string
    {
        return (string) ($familyStudents
            ->pluck('class_name')
            ->map(fn ($className): string => trim((string) $className))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first() ?? '-');
    }
}
