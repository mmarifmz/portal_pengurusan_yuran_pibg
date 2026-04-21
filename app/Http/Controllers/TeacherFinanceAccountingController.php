<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            'currentYear' => $dataset['currentYear'],
            'sortBy' => $dataset['sortBy'],
            'sortDir' => $dataset['sortDir'],
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
     *   rows: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *   totals: array<string, float>,
     *   search: string,
     *   classFilter: string,
     *   classOptions: \Illuminate\Support\Collection<int, string>,
     *   yearA: int, yearB: int, currentYear: int, sortBy: string, sortDir: string
     * }
     */
    private function buildDataset(Request $request): array
    {
        $yearA = (int) $request->integer('year_a', 2025);
        $yearB = (int) $request->integer('year_b', 2026);
        $search = trim((string) $request->query('search', ''));
        $classFilter = trim((string) $request->query('class_name', ''));
        $sortBy = trim((string) $request->query('sort_by', 'current_year'));
        $sortDir = trim((string) $request->query('sort_dir', 'desc'));

        if ($yearA < 2000 || $yearA > 2100) {
            $yearA = 2025;
        }

        if ($yearB < 2000 || $yearB > 2100) {
            $yearB = 2026;
        }
        if (! in_array($sortBy, ['name', 'current_year', 'current_year_sumbangan'], true)) {
            $sortBy = 'current_year';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $nowYear = (int) now()->year;
        $currentYear = in_array($nowYear, [$yearA, $yearB], true) ? $nowYear : $yearB;

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

        $portalPaymentByYearFamily = FamilyPaymentTransaction::query()
            ->selectRaw('family_billings.billing_year, family_billings.family_code')
            ->selectRaw('COUNT(*) as row_count')
            ->selectRaw('SUM(COALESCE(family_payment_transactions.amount, 0)) as amount_sum')
            ->selectRaw('SUM(COALESCE(family_payment_transactions.fee_amount_paid, 0)) as fee_sum')
            ->selectRaw('SUM(COALESCE(family_payment_transactions.donation_amount, 0)) as donation_sum')
            ->join('family_billings', 'family_billings.id', '=', 'family_payment_transactions.family_billing_id')
            ->where('family_payment_transactions.status', 'success')
            ->whereIn('family_billings.billing_year', [$yearA, $yearB])
            ->groupBy('family_billings.billing_year', 'family_billings.family_code')
            ->get()
            ->groupBy(fn ($row): string => (string) $row->billing_year)
            ->map(fn (Collection $yearRows): Collection => $yearRows->keyBy(fn ($row): string => (string) $row->family_code));

        $legacyDedupeSubquery = LegacyStudentPayment::query()
            ->selectRaw('source_year')
            ->selectRaw('family_code')
            ->selectRaw("COALESCE(NULLIF(payment_reference, ''), CONCAT('LEGACY-', id)) as dedupe_ref")
            ->selectRaw('MAX(COALESCE(amount_paid, 0)) as amount_paid')
            ->selectRaw('MAX(COALESCE(donation_amount, 0)) as donation_amount')
            ->where('payment_status', 'paid')
            ->whereIn('source_year', [$yearA, $yearB])
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->groupBy('source_year', 'family_code', 'dedupe_ref');

        $legacyPaymentByYearFamily = DB::query()
            ->fromSub($legacyDedupeSubquery, 'legacy_rows')
            ->selectRaw('source_year')
            ->selectRaw('family_code')
            // Legacy snapshots can be cumulative; take latest/max instead of summing across rows.
            ->selectRaw('MAX(COALESCE(amount_paid, 0)) as amount_sum')
            ->selectRaw('MAX(COALESCE(donation_amount, 0)) as donation_sum')
            ->groupBy('source_year', 'family_code')
            ->get()
            ->groupBy(fn ($row): string => (string) $row->source_year)
            ->map(fn (Collection $yearRows): Collection => $yearRows->keyBy(fn ($row): string => (string) $row->family_code));

        $classOptions = $studentsByFamily
            ->map(fn (Collection $familyStudents): string => $this->resolveClassName($familyStudents))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $rows = $studentsByFamily
            ->map(function (Collection $familyStudents, string $familyCode) use (
                $billingByYearAndFamily,
                $portalPaymentByYearFamily,
                $legacyPaymentByYearFamily,
                $yearA,
                $yearB,
                $currentYear
            ): array {
                $billingA = $billingByYearAndFamily->get($yearA)?->get($familyCode);
                $billingB = $billingByYearAndFamily->get($yearB)?->get($familyCode);
                $portalA = $portalPaymentByYearFamily->get((string) $yearA)?->get($familyCode);
                $portalB = $portalPaymentByYearFamily->get((string) $yearB)?->get($familyCode);
                $legacyA = $legacyPaymentByYearFamily->get((string) $yearA)?->get($familyCode);
                $legacyB = $legacyPaymentByYearFamily->get((string) $yearB)?->get($familyCode);

                [$yuranA, $sumbanganA] = $this->resolveYearBreakdown($billingA, $portalA, $legacyA);
                [$yuranB, $sumbanganB] = $this->resolveYearBreakdown($billingB, $portalB, $legacyB);

                $row = [
                    'family_code' => $familyCode,
                    'name' => $this->resolveDisplayName($familyStudents),
                    'class_name' => $this->resolveClassName($familyStudents),
                    'students' => $this->buildStudentItems($familyStudents),
                    "yuran_{$yearA}" => $yuranA,
                    "sumbangan_{$yearA}" => $sumbanganA,
                    "yuran_{$yearB}" => $yuranB,
                    "sumbangan_{$yearB}" => $sumbanganB,
                ];

                $row['current_year_total'] = (float) ($row["yuran_{$currentYear}"] + $row["sumbangan_{$currentYear}"]);
                $row['current_year_sumbangan'] = (float) $row["sumbangan_{$currentYear}"];

                return $row;
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

        $rows = $rows
            ->sort(function (array $a, array $b) use ($sortBy, $sortDir): int {
                if ($sortBy === 'name') {
                    $aName = mb_strtolower((string) ($a['name'] ?? ''));
                    $bName = mb_strtolower((string) ($b['name'] ?? ''));

                    if ($aName !== $bName) {
                        return $sortDir === 'asc'
                            ? strcmp($aName, $bName)
                            : strcmp($bName, $aName);
                    }
                } elseif ($sortBy === 'current_year_sumbangan') {
                    $aVal = (float) ($a['current_year_sumbangan'] ?? 0);
                    $bVal = (float) ($b['current_year_sumbangan'] ?? 0);

                    if ($aVal !== $bVal) {
                        return $sortDir === 'asc'
                            ? ($aVal <=> $bVal)
                            : ($bVal <=> $aVal);
                    }
                } else {
                    $aVal = (float) ($a['current_year_total'] ?? 0);
                    $bVal = (float) ($b['current_year_total'] ?? 0);

                    if ($aVal !== $bVal) {
                        return $sortDir === 'asc'
                            ? ($aVal <=> $bVal)
                            : ($bVal <=> $aVal);
                    }
                }

                return strcmp((string) ($a['family_code'] ?? ''), (string) ($b['family_code'] ?? ''));
            })
            ->values();

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
            'currentYear' => $currentYear,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
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

    private function buildStudentItems(Collection $familyStudents): array
    {
        return $familyStudents
            ->map(function (Student $student): array {
                return [
                    'full_name' => trim((string) $student->full_name) ?: '-',
                    'class_name' => trim((string) $student->class_name) ?: '-',
                ];
            })
            ->sortBy(fn (array $row): string => mb_strtolower($row['full_name']))
            ->values()
            ->all();
    }

    /**
     * @param object|null $billing
     * @param object|null $portalAgg
     * @param object|null $legacyAgg
     * @return array{0: float, 1: float}
     */
    private function resolveYearBreakdown($billing, $portalAgg, $legacyAgg): array
    {
        $feeAmount = max(0, (float) ($billing->fee_amount ?? 0));
        $billingPaid = max(0, (float) ($billing->paid_amount ?? 0));

        if ($portalAgg && (int) ($portalAgg->row_count ?? 0) > 0) {
            $amountSum = max(0, (float) ($portalAgg->amount_sum ?? 0));
            $feeSum = max(0, (float) ($portalAgg->fee_sum ?? 0));
            $donationSum = max(0, (float) ($portalAgg->donation_sum ?? 0));

            $yuran = $feeSum > 0
                ? min($feeSum, $feeAmount > 0 ? $feeAmount : $feeSum)
                : min($amountSum, $feeAmount > 0 ? $feeAmount : $amountSum);

            $sumbangan = $donationSum > 0
                ? $donationSum
                : max(0, $amountSum - $yuran);

            return [round($yuran, 2), round($sumbangan, 2)];
        }

        if ($legacyAgg) {
            $legacyAmount = max(0, (float) ($legacyAgg->amount_sum ?? 0));
            $legacyDonation = max(0, (float) ($legacyAgg->donation_sum ?? 0));
            $legacyYuran = max(0, $legacyAmount - $legacyDonation);

            return [round($legacyYuran, 2), round($legacyDonation, 2)];
        }

        return $this->splitYuranAndSumbangan($billingPaid, $feeAmount);
    }
}
