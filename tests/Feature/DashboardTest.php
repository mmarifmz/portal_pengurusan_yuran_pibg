<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\LegacyStudentPayment;
use App\Models\ParentLoginAudit;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard year selector excludes future years', function () {
    $currentYear = (int) now()->year;
    $futureYear = $currentYear + 1;

    FamilyBilling::query()->create([
        'family_code' => 'FAM-CURR',
        'billing_year' => $currentYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'FAM-FUTURE',
        'billing_year' => $futureYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $user = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('name="dashboard_year"', false);
    $response->assertSee('value="'.$currentYear.'"', false);
    $response->assertDontSee('value="'.$futureYear.'"', false);
});

test('dashboard compares previous year payers with their current year payment status', function () {
    $currentYear = (int) now()->year;
    $previousYear = $currentYear - 1;

    $previousPayerCodes = [
        'COHORT-PAID',
        'COHORT-PARTIAL',
        'COHORT-UNPAID',
        'COHORT-MISSING-BILLING',
        'COHORT-INACTIVE',
    ];

    foreach ($previousPayerCodes as $index => $familyCode) {
        LegacyStudentPayment::query()->create([
            'student_no' => 'LEGACY-'.$index,
            'family_code' => $familyCode,
            'student_name' => 'Legacy Student '.$index,
            'class_name' => '6 Amanah',
            'source_year' => $previousYear,
            'payment_status' => 'paid',
            'amount_due' => 100,
            'amount_paid' => 100,
            'payment_reference' => 'LEGACY-COHORT-'.$index,
        ]);
    }

    foreach (array_slice($previousPayerCodes, 0, 4) as $index => $familyCode) {
        Student::query()->create([
            'student_no' => 'CURRENT-'.$index,
            'family_code' => $familyCode,
            'full_name' => 'Current Student '.$index,
            'class_name' => '1 Bestari',
            'status' => Student::STATUS_ACTIVE,
            'billing_year' => $currentYear,
        ]);
    }

    FamilyBilling::query()->create([
        'family_code' => 'COHORT-PAID',
        'billing_year' => $currentYear,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'COHORT-PARTIAL',
        'billing_year' => $currentYear,
        'fee_amount' => 100,
        'paid_amount' => 40,
        'status' => 'partial',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'COHORT-UNPAID',
        'billing_year' => $currentYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $admin = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewHas('previousPayerCohort', function (array $cohort) use ($currentYear, $previousYear): bool {
        return $cohort['previous_year'] === $previousYear
            && $cohort['current_year'] === $currentYear
            && $cohort['previous_paid_families'] === 5
            && $cohort['active_current_year_families'] === 4
            && $cohort['inactive_or_departed_families'] === 1
            && $cohort['fully_paid_families'] === 1
            && $cohort['partial_paid_families'] === 1
            && $cohort['unpaid_families'] === 1
            && $cohort['missing_billing_families'] === 1
            && $cohort['unpaid_percentage'] === 33.3;
    });
    $response->assertSee('Petunjuk pembayar berulang');
    $response->assertSee('Belum membuat bayaran');
    $response->assertSee('id="previousPayerCohortChart"', false);
});

test('dashboard compares cumulative collections at 30 60 and 90 days from each first payment date', function () {
    $currentYear = (int) now()->year;
    $previousYear = $currentYear - 1;
    $timezone = (string) config('app.timezone', 'Asia/Kuala_Lumpur');
    $previousStart = Carbon::create($previousYear, 1, 1, 9, 0, 0, $timezone);
    $currentStart = Carbon::create($currentYear, 2, 1, 9, 0, 0, $timezone);

    $legacyRows = [
        ['family' => 'LEGACY-A', 'student' => 'Legacy A1', 'reference' => 'PREVIOUS-START', 'days' => 0, 'amount' => 100],
        ['family' => 'LEGACY-A', 'student' => 'Legacy A2', 'reference' => 'PREVIOUS-START', 'days' => 0, 'amount' => 100],
        ['family' => 'LEGACY-B', 'student' => 'Legacy B', 'reference' => 'PREVIOUS-DAY-30', 'days' => 29, 'amount' => 50],
        ['family' => 'LEGACY-C', 'student' => 'Legacy C', 'reference' => 'PREVIOUS-DAY-45', 'days' => 44, 'amount' => 25],
        ['family' => 'LEGACY-D', 'student' => 'Legacy D', 'reference' => 'PREVIOUS-DAY-75', 'days' => 74, 'amount' => 10],
        ['family' => 'LEGACY-E', 'student' => 'Legacy E', 'reference' => 'PREVIOUS-DAY-91', 'days' => 90, 'amount' => 500],
    ];

    foreach ($legacyRows as $index => $row) {
        LegacyStudentPayment::query()->create([
            'student_no' => 'WINDOW-LEGACY-'.$index,
            'family_code' => $row['family'],
            'student_name' => $row['student'],
            'class_name' => '6 Amanah',
            'source_year' => $previousYear,
            'payment_status' => 'paid',
            'amount_due' => 100,
            'amount_paid' => $row['amount'],
            'payment_reference' => $row['reference'],
            'paid_at' => $previousStart->copy()->addDays($row['days']),
        ]);
    }

    $billing = FamilyBilling::query()->create([
        'family_code' => 'CURRENT-WINDOW',
        'billing_year' => $currentYear,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    foreach ([
        ['days' => 0, 'amount' => 120],
        ['days' => 29, 'amount' => 120],
        ['days' => 44, 'amount' => 60],
        ['days' => 74, 'amount' => 40],
        ['days' => 90, 'amount' => 600],
    ] as $index => $row) {
        FamilyPaymentTransaction::query()->create([
            'family_billing_id' => $billing->id,
            'payment_provider' => 'toyyibpay',
            'external_order_id' => 'CURRENT-WINDOW-'.$index,
            'amount' => $row['amount'],
            'status' => 'success',
            'paid_at' => $currentStart->copy()->addDays($row['days']),
        ]);
    }

    $admin = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewHas('collectionWindowComparison', function (array $comparison) use ($currentYear, $previousYear): bool {
        return $comparison['previous_year']['year'] === $previousYear
            && $comparison['previous_year']['start_date'] === $previousYear.'-01-01'
            && $comparison['previous_year']['transaction_count'] === 5
            && $comparison['current_year']['year'] === $currentYear
            && $comparison['current_year']['start_date'] === $currentYear.'-02-01'
            && $comparison['current_year']['transaction_count'] === 5
            && $comparison['windows'][0]['previous_amount'] === 150.0
            && $comparison['windows'][0]['current_amount'] === 240.0
            && $comparison['windows'][1]['previous_amount'] === 175.0
            && $comparison['windows'][1]['current_amount'] === 300.0
            && $comparison['windows'][2]['previous_amount'] === 185.0
            && $comparison['windows'][2]['current_amount'] === 340.0
            && $comparison['all_windows_complete'] === true;
    });
    $response->assertSee('Kutipan 30, 60 dan 90 hari');
    $response->assertSee('id="collectionWindowComparisonChart"', false);
});

test('school calendar year selector excludes future years', function () {
    $currentYear = (int) now()->year;
    $futureYear = $currentYear + 1;

    FamilyBilling::query()->create([
        'family_code' => 'CAL-CURR',
        'billing_year' => $currentYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'CAL-FUTURE',
        'billing_year' => $futureYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $user = User::factory()->create([
        'role' => 'parent',
    ]);

    $response = $this->actingAs($user)->get(route('school-calendar'));

    $response->assertOk();
    $response->assertSee('name="dashboard_year"', false);
    $response->assertSee('value="'.$currentYear.'"', false);
    $response->assertDontSee('value="'.$futureYear.'"', false);
});

test('school calendar exposes daily paid and parent activity counts for staff', function () {
    $date = now()->setDate((int) now()->year, 6, 3)->startOfDay();

    $billing = FamilyBilling::query()->create([
        'family_code' => 'CAL-ACTIVITY-001',
        'billing_year' => $date->year,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'CAL-ACTIVITY-PAID-1',
        'amount' => 100,
        'status' => 'success',
        'paid_at' => $date->copy()->setTime(9, 0),
    ]);

    FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'CAL-ACTIVITY-PAID-2',
        'amount' => 10,
        'status' => 'success',
        'paid_at' => $date->copy()->setTime(10, 0),
    ]);

    ParentLoginAudit::query()->create([
        'phone' => '0123000002',
        'normalized_phone' => '60123000002',
        'action_type' => 'login',
        'access_status' => 'successful',
        'logged_in_at' => $date->copy()->setTime(8, 0),
        'occurred_at' => $date->copy()->setTime(8, 0),
    ]);

    ParentLoginAudit::query()->create([
        'phone' => '0123000003',
        'normalized_phone' => '60123000003',
        'action_type' => 'login',
        'access_status' => 'successful',
        'logged_in_at' => $date->copy()->setTime(8, 2),
        'occurred_at' => $date->copy()->setTime(8, 2),
    ]);

    ParentLoginAudit::query()->create([
        'phone' => '0123000002',
        'normalized_phone' => '60123000002',
        'action_type' => 'viewed_payment',
        'access_status' => 'successful',
        'logged_in_at' => $date->copy()->setTime(8, 5),
        'occurred_at' => $date->copy()->setTime(8, 5),
    ]);

    ParentLoginAudit::query()->create([
        'phone' => '0123000002',
        'normalized_phone' => '60123000002',
        'action_type' => 'viewed_receipt',
        'access_status' => 'successful',
        'logged_in_at' => $date->copy()->setTime(8, 7),
        'occurred_at' => $date->copy()->setTime(8, 7),
    ]);

    ParentLoginAudit::query()->create([
        'phone' => '0123000002',
        'normalized_phone' => '60123000002',
        'action_type' => 'clicked_pay_now',
        'access_status' => 'successful',
        'logged_in_at' => $date->copy()->setTime(8, 9),
        'occurred_at' => $date->copy()->setTime(8, 9),
    ]);

    $admin = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $response = $this->actingAs($admin)->get(route('school-calendar', [
        'dashboard_year' => $date->year,
    ]));

    $response->assertOk();
    $response->assertSee('const paidCountByDate = {"'.$date->toDateString().'":1};', false);
    $response->assertSee('const loginCountByDate = {"'.$date->toDateString().'":2};', false);
    $response->assertSee('const visitCountByDate = {"'.$date->toDateString().'":3};', false);

    $parent = User::factory()->create([
        'role' => 'parent',
    ]);

    $parentResponse = $this->actingAs($parent)->get(route('school-calendar', [
        'dashboard_year' => $date->year,
    ]));

    $parentResponse->assertOk();
    $parentResponse->assertSee('const paidCountByDate = [];', false);
    $parentResponse->assertSee('const loginCountByDate = [];', false);
    $parentResponse->assertSee('const visitCountByDate = [];', false);
    $parentResponse->assertDontSee('bilangan bayaran harian', false);

    $pta = User::factory()->create([
        'role' => 'pta',
    ]);

    $ptaResponse = $this->actingAs($pta)->get(route('school-calendar', [
        'dashboard_year' => $date->year,
    ]));

    $ptaResponse->assertOk();
    $ptaResponse->assertSee('const paidCountByDate = [];', false);
    $ptaResponse->assertSee('const loginCountByDate = [];', false);
    $ptaResponse->assertSee('const visitCountByDate = [];', false);
    $ptaResponse->assertDontSee('bilangan bayaran harian', false);
});

test('payment funnel billing year filter excludes future years', function () {
    $currentYear = (int) now()->year;
    $futureYear = $currentYear + 1;

    FamilyBilling::query()->create([
        'family_code' => 'FUN-CURR',
        'billing_year' => $currentYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'FUN-FUTURE',
        'billing_year' => $futureYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $user = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $response = $this->actingAs($user)->get(route('system.payment-funnel-monitor.index'));

    $response->assertOk();
    $response->assertSee((string) $currentYear);
    $response->assertDontSee((string) $futureYear);
});

test('teacher cannot access payment funnel monitor', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
    ]);

    $response = $this->actingAs($teacher)->get(route('system.payment-funnel-monitor.index'));

    $response->assertForbidden();
});

test('teacher cannot access finance accounting dashboard', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.finance-accounting'));

    $response->assertForbidden();
});
