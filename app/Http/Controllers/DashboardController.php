<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\SchoolCalendarEvent;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $role = $user?->isParent() ? 'parent' : 'staff';
        $billingYear = now()->year;

        $familyBillings = FamilyBilling::with('students')
            ->where('billing_year', $billingYear)
            ->orderBy('family_code')
            ->get();

        $students = Student::where('billing_year', $billingYear)
            ->orderBy('family_code')
            ->orderBy('class_name')
            ->orderBy('full_name')
            ->get();

        $totalFamilies = $familyBillings->count();
        $totalStudents = $students->count();
        $totalCollected = (float) $familyBillings->sum('paid_amount');
        $totalOutstanding = (float) $familyBillings->sum(fn (FamilyBilling $billing) => $billing->outstanding_amount);
        $totalBilled = (float) $familyBillings->sum('fee_amount');
        $fullyPaid = $familyBillings->filter(fn (FamilyBilling $billing) => $billing->outstanding_amount <= 0)->count();
        $paymentCompletion = $totalFamilies > 0 ? (int) round(($fullyPaid / $totalFamilies) * 100) : 0;

        $classStats = $students->groupBy(fn (Student $student) => $student->class_name ?: 'Unassigned')
            ->map(fn ($group, string $className) => [
                'class_name' => $className,
                'students' => $group->count(),
                'outstanding' => $group->sum(fn (Student $student) => $student->total_fee - $student->paid_amount),
                'total_fee' => $group->sum('total_fee'),
            ])
            ->sortByDesc('students')
            ->values()
            ->toArray();

        $classChartLabels = collect($classStats)->pluck('class_name')->map(fn ($label) => (string) $label)->toArray();
        $classChartOutstanding = collect($classStats)->pluck('outstanding')->map(fn ($value) => round($value, 2))->toArray();
        $classChartCollected = collect($classStats)->map(fn ($item) => round(max(0, $item['total_fee'] - $item['outstanding']), 2))->toArray();

        $monthlyTotals = FamilyPaymentTransaction::query()
            ->whereYear('paid_at', $billingYear)
            ->whereNotNull('paid_at')
            ->get()
            ->groupBy(fn (FamilyPaymentTransaction $transaction) => $transaction->paid_at->format('M'))
            ->map(fn ($group) => $group->sum('amount'));

        $monthlyLabels = [];
        $monthlyValues = [];
        for ($month = 1; $month <= 12; $month++) {
            $label = Carbon::create($billingYear, $month, 1)->format('M');
            $monthlyLabels[] = $label;
            $monthlyValues[] = round($monthlyTotals->get($label, 0), 2);
        }

        $dailyWindowStart = now()->subDays(13)->startOfDay();
        $dailyWindowLabels = collect(range(0, 13))->mapWithKeys(function (int $offset) use ($dailyWindowStart) {
            $date = $dailyWindowStart->copy()->addDays($offset);
            return [$date->format('Y-m-d') => $date->format('d M')];
        });

        $dailyThen = FamilyPaymentTransaction::query()
            ->whereBetween('paid_at', [$dailyWindowStart, now()->endOfDay()])
            ->whereNotNull('paid_at')
            ->get()
            ->groupBy(fn (FamilyPaymentTransaction $transaction) => $transaction->paid_at->format('Y-m-d'))
            ->map(fn ($group) => $group->sum('amount'));

        $dailyTrendLabels = $dailyWindowLabels->values()->toArray();
        $dailyTrendValues = $dailyWindowLabels->keys()
            ->map(fn (string $key) => round($dailyThen->get($key, 0), 2))
            ->values()
            ->toArray();

        $paidFamilies = $fullyPaid;
        $pieData = [
            'Paid' => $paidFamilies,
            'Unpaid' => max(0, $familyBillings->count() - $paidFamilies),
        ];

        $familyContribution = $familyBillings->map(function (FamilyBilling $billing) {
            $students = $billing->students;

            return [
                'family_code' => $billing->family_code,
                'guardian' => $students->pluck('parent_name')->filter()->first() ?? 'â€”',
                'children' => $students->count(),
                'classes' => $students->pluck('class_name')->filter()->unique()->join(', '),
                'amount_due' => (float) $billing->fee_amount,
                'status' => Str::title($billing->status),
                'comment' => $this->familyComment($billing),
                'children_list' => $students->map(fn (Student $student) => [
                    'full_name' => $student->full_name,
                    'class_name' => $student->class_name,
                    'status' => $student->status,
                ])->values()->toArray(),
            ];
        })->values();

        $recentActivities = FamilyPaymentTransaction::query()
            ->with('familyBilling')
            ->latest('paid_at')
            ->limit(5)
            ->get();

        $transactionsForParent = $this->parentTransactions($user);
        $transactionsByYear = $transactionsForParent
            ->filter(fn (FamilyPaymentTransaction $transaction) => $transaction->status === 'success')
            ->groupBy(fn (FamilyPaymentTransaction $transaction) => $transaction->paid_at?->year ?? now()->year);

        $accessLogs = $this->recentAccessEntries();
        $calendarEvents = SchoolCalendarEvent::query()
            ->orderBy('start_date')
            ->orderBy('sort_order')
            ->get();

        return view('dashboard', [
            'role' => $role,
            'totalFamilies' => $totalFamilies,
            'totalStudents' => $totalStudents,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'totalBilled' => $totalBilled,
            'paymentCompletion' => $paymentCompletion,
            'classStats' => $classStats,
            'classChartLabels' => $classChartLabels,
            'classChartOutstanding' => $classChartOutstanding,
            'classChartCollected' => $classChartCollected,
            'monthlyLabels' => $monthlyLabels,
            'monthlyValues' => $monthlyValues,
            'dailyTrendLabels' => $dailyTrendLabels,
            'dailyTrendValues' => $dailyTrendValues,
            'pieChartLabels' => array_keys($pieData),
            'pieChartValues' => array_values($pieData),
            'familyContribution' => $familyContribution,
            'recentActivities' => $recentActivities,
            'accessLogs' => $accessLogs,
            'transactions' => $transactionsForParent,
            'transactionsByYear' => $transactionsByYear,
            'calendarEvents' => $calendarEvents,
        ]);
    }

    public function submitParentMessage(Request $request): RedirectResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $entry = sprintf(
            "[%s] %s (%s): %s\\n",
            now()->format('Y-m-d H:i:s'),
            $user?->name ?? 'Guest',
            $user?->email ?? 'anonymous',
            $request->input('message')
        );

        File::append(storage_path('logs/parent_messages.log'), $entry);

        $portalUrl = route('home');

        $waText = "Assalamualaikum Bendahari PIBG,

"
            .$request->input('message')
            ."

Daripada:
"
            .($user?->name ?? 'Guest')
            .' ('.($user?->email ?? 'anonymous').')'
            ."

Portal Yuran:
"
            .$portalUrl;

        $treasuryPhone = $this->normalizeWaPhone((string) config('services.treasury_whatsapp_phone', '60136454001'));

        return redirect()->away('https://wa.me/'.$treasuryPhone.'?text='.rawurlencode($waText));
    }

    private function parentTransactions($user)
    {
        $query = FamilyPaymentTransaction::query()->with('familyBilling')->latest('paid_at');

        if ($user) {
            $query->where(function ($builder) use ($user) {
                $builder->where('user_id', $user->id);

                if ($user->phone) {
                    $builder->orWhere('payer_phone', $user->phone);
                }

                if ($user->email) {
                    $builder->orWhere('payer_email', $user->email);
                }
            });
        }

        $transactions = $query->limit(5)->get();

        if ($transactions->isEmpty()) {
            $transactions = FamilyPaymentTransaction::query()
                ->with('familyBilling')
                ->latest('paid_at')
                ->limit(5)
                ->get();
        }

        return $transactions;
    }

    private function familyComment(FamilyBilling $billing): string
    {
        $comments = [];

        if ($billing->notes) {
            $comments[] = Str::headline($billing->notes);
        }

        if ($billing->students->contains(fn (Student $student) => blank($student->parent_phone) && blank($student->parent_email))) {
            $comments[] = 'Missing parent contact';
        }

        if ($billing->outstanding_amount > 0 && $billing->paid_amount > 0) {
            $comments[] = 'Partial payment';
        }

        if ($billing->status === 'unpaid') {
            $comments[] = 'Awaiting payment';
        }

        return $comments ? implode(', ', array_unique($comments)) : 'â€”';
    }

    private function recentAccessEntries(): array
    {
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            return [];
        }

        $lines = array_filter(array_map('trim', array_slice(array_reverse(file($path)), 0, 5)));

        return $lines;
    }

    private function normalizeWaPhone(string $phone): string
    {
        $digits = preg_replace('/\\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '60136454001';
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
}


