<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\ParentLoginAudit;
use App\Models\SchoolCalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SchoolCalendarPageController extends Controller
{
    public function index(Request $request): View
    {
        $currentYear = (int) now()->year;

        $yearOptions = collect()
            ->merge(FamilyBilling::query()->distinct()->pluck('billing_year'))
            ->merge(LegacyStudentPayment::query()->distinct()->pluck('source_year'))
            ->merge([$currentYear])
            ->filter(fn ($year) => is_numeric($year))
            ->map(fn ($year) => (int) $year)
            ->filter(fn (int $year): bool => $year <= $currentYear)
            ->unique()
            ->sortDesc()
            ->values();

        if ($yearOptions->isEmpty()) {
            $yearOptions = collect([$currentYear]);
        }

        $selectedDashboardYear = (int) $request->integer('dashboard_year', (int) $yearOptions->first());
        if (! $yearOptions->contains($selectedDashboardYear)) {
            $selectedDashboardYear = (int) $yearOptions->first();
        }

        $canViewCalendarCounts = (bool) $request->user()?->hasAnyRole([
            'teacher',
            'super_teacher',
            'system_admin',
            'admin',
            'super_admin',
        ]);

        $calendarPaidCountByDate = [];
        $calendarActivityCounts = ['login' => [], 'visit' => []];

        if ($canViewCalendarCounts) {
            $legacyPayments = LegacyStudentPayment::query()
                ->where('source_year', $selectedDashboardYear)
                ->where('payment_status', 'paid')
                ->get();

            $familyBillings = FamilyBilling::query()
                ->where('billing_year', $selectedDashboardYear)
                ->get();

            $useLegacyKpiSource = $familyBillings->isEmpty() && $legacyPayments->isNotEmpty();

            if ($useLegacyKpiSource) {
                $calendarPaidCountByDate = $legacyPayments
                    ->filter(fn (LegacyStudentPayment $payment) => $payment->paid_at !== null)
                    ->groupBy(fn (LegacyStudentPayment $payment) => $payment->paid_at->format('Y-m-d'))
                    ->map(fn (Collection $group) => $group
                        ->map(fn (LegacyStudentPayment $payment): string => trim((string) ($payment->family_code ?: $payment->student_id ?: $payment->id)))
                        ->filter()
                        ->unique()
                        ->count())
                    ->toArray();
            } else {
                $calendarPaidCountByDate = FamilyPaymentTransaction::query()
                    ->where('status', 'success')
                    ->whereYear('paid_at', $selectedDashboardYear)
                    ->whereNotNull('paid_at')
                    ->get()
                    ->groupBy(fn (FamilyPaymentTransaction $transaction) => $transaction->paid_at->format('Y-m-d'))
                    ->map(fn (Collection $group) => $group
                        ->pluck('family_billing_id')
                        ->filter()
                        ->unique()
                        ->count())
                    ->toArray();
            }

            $calendarActivityCounts = $this->calendarActivityCountsByDate($selectedDashboardYear);
        }

        $calendarEvents = SchoolCalendarEvent::query()
            ->orderBy('start_date')
            ->orderBy('sort_order')
            ->get();

        return view('school-calendar', [
            'dashboardYearOptions' => $yearOptions->toArray(),
            'selectedDashboardYear' => $selectedDashboardYear,
            'calendarEvents' => $calendarEvents,
            'calendarPaidCountByDate' => $calendarPaidCountByDate,
            'calendarLoginCountByDate' => $calendarActivityCounts['login'],
            'calendarVisitCountByDate' => $calendarActivityCounts['visit'],
            'canViewCalendarCounts' => $canViewCalendarCounts,
        ]);
    }

    /**
     * @return array{login: array<string, int>, visit: array<string, int>}
     */
    private function calendarActivityCountsByDate(int $year): array
    {
        if (! ParentLoginAudit::tableIsAvailable()) {
            return ['login' => [], 'visit' => []];
        }

        $occurrenceColumn = ParentLoginAudit::occurrenceColumn();
        $query = ParentLoginAudit::query()
            ->whereNotNull($occurrenceColumn)
            ->whereYear($occurrenceColumn, $year);

        if (ParentLoginAudit::hasAuditColumn('access_status')) {
            $query->where(function ($builder): void {
                $builder->whereNull('access_status')
                    ->orWhere('access_status', 'successful');
            });
        }

        $selectColumns = [$occurrenceColumn];
        if (ParentLoginAudit::hasAuditColumn('action_type')) {
            $selectColumns[] = 'action_type';
        }

        $rows = $query
            ->get($selectColumns)
            ->groupBy(function (ParentLoginAudit $audit) use ($occurrenceColumn): string {
                return Carbon::parse($audit->{$occurrenceColumn})
                    ->timezone(config('app.timezone', 'Asia/Kuala_Lumpur'))
                    ->format('Y-m-d');
            });

        $loginActions = ['login'];
        $visitActions = [
            'viewed_dashboard',
            'viewed_payment',
            'viewed_receipt',
            'downloaded_receipt',
            'changed_payment_option',
            'clicked_pay_now',
            'parent_space_opened',
        ];

        return [
            'login' => $rows
                ->map(fn (Collection $group): int => $group
                    ->filter(fn (ParentLoginAudit $audit): bool => in_array((string) ($audit->action_type ?: 'login'), $loginActions, true))
                    ->count())
                ->filter(fn (int $count): bool => $count > 0)
                ->toArray(),
            'visit' => $rows
                ->map(fn (Collection $group): int => $group
                    ->filter(fn (ParentLoginAudit $audit): bool => in_array((string) $audit->action_type, $visitActions, true))
                    ->count())
                ->filter(fn (int $count): bool => $count > 0)
                ->toArray(),
        ];
    }
}
