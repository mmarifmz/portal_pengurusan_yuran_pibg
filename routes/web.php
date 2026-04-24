<?php

use App\Http\Controllers\BillingSetupController;
use App\Http\Controllers\ParentDashboardController;
use App\Http\Controllers\ParentOtpAuthController;
use App\Http\Controllers\ParentPaymentController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SchoolCalendarEventController;
use App\Http\Controllers\PtaDashboardController;
use App\Http\Controllers\PublicParentSearchController;
use App\Http\Controllers\StudentFamilyController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\TeacherReconciliationController;
use App\Http\Controllers\TeacherRecordsController;
use App\Http\Controllers\TeacherUserManagementController;
use App\Http\Controllers\TeacherFamilyLoginMonitorController;
use App\Http\Controllers\TeacherFinanceAccountingController;
use App\Http\Controllers\TeacherClassProgressController;
use App\Http\Controllers\PaymentTesterUserController;
use App\Http\Controllers\PortalSeoSettingsController;
use App\Http\Controllers\VisitorLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentFunnelMonitorController;
use App\Http\Controllers\ParentInviteAuthController;
use App\Http\Controllers\SchoolCalendarPageController;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $classOptions = Student::query()
        ->whereNotNull('class_name')
        ->where('class_name', '!=', '')
        ->distinct()
        ->orderBy('class_name')
        ->pluck('class_name')
        ->values();

    $recentTransactions = FamilyPaymentTransaction::query()
        ->with('familyBilling:id,family_code,billing_year')
        ->where('status', 'success')
        ->whereNotNull('paid_at')
        ->orderByDesc('paid_at')
        ->limit(20)
        ->get();

    $familyCodes = $recentTransactions
        ->pluck('familyBilling.family_code')
        ->filter()
        ->unique()
        ->values();

    $dominantClassByFamily = Student::query()
        ->whereIn('family_code', $familyCodes)
        ->select(['family_code', 'class_name'])
        ->get()
        ->groupBy('family_code')
        ->map(function ($familyStudents): string {
            return (string) ($familyStudents
                ->pluck('class_name')
                ->map(fn ($className) => trim((string) $className))
                ->filter()
                ->countBy()
                ->sortDesc()
                ->keys()
                ->first() ?? 'Unknown Class');
        });

    $recentPaymentToasts = $recentTransactions
        ->map(function (FamilyPaymentTransaction $transaction) use ($dominantClassByFamily): ?string {
            $familyCode = (string) ($transaction->familyBilling?->family_code ?? '');
            if ($familyCode === '') {
                return null;
            }

            $className = (string) ($dominantClassByFamily->get($familyCode) ?: 'Unknown Class');
            $donation = (float) ($transaction->donation_amount ?? 0);

            if ($donation <= 0) {
                $donation = max(0, (float) $transaction->amount - (float) ($transaction->fee_amount_paid ?? 0));
            }

            if ($donation > 0) {
                return "Parent in {$className} just paid Yuran + Sumbangan Tambahan";
            }

            return "Parent in {$className} just paid Yuran";
        })
        ->filter()
        ->unique()
        ->take(10)
        ->values();

    return view('welcome', [
        'classOptions' => $classOptions,
        'recentPaymentToasts' => $recentPaymentToasts,
    ]);
})->name('home');

Route::get('/parent/search', [PublicParentSearchController::class, 'index'])
    ->name('parent.search');
Route::get('/receipts/{receiptUuid}', [ReceiptController::class, 'show'])->name('receipts.show');

Route::middleware('guest')->prefix('parent/login')->name('parent.login.')->group(function () {
    Route::get('/', [ParentOtpAuthController::class, 'showRequestForm'])->name('form');
    Route::post('/request', [ParentOtpAuthController::class, 'sendTac'])->name('request');
    Route::get('/verify', [ParentOtpAuthController::class, 'showVerifyForm'])->name('verify.form');
    Route::post('/verify', [ParentOtpAuthController::class, 'verifyTac'])->name('verify.submit');
});

Route::get('/parent/invite/{token}', ParentInviteAuthController::class)
    ->name('parent.invite.login');

// ToyyibPay return/callback must stay public (gateway/browser call outside auth middleware).
Route::get('/parent/payments/summary/return', [ParentPaymentController::class, 'handleReturn'])
    ->name('parent.payments.summary.return');
Route::post('/parent/payments/callback', [ParentPaymentController::class, 'handleCallback'])
    ->name('parent.payments.toyyibpay.callback');

// Legacy aliases for older bill configurations.
Route::get('/payment-return', [ParentPaymentController::class, 'handleReturn']);
Route::post('/payment-webhook', [ParentPaymentController::class, 'handleCallback']);

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('teacher/dashboard', [TeacherRecordsController::class, 'index'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.dashboard');
    Route::get('school-calendar', [SchoolCalendarPageController::class, 'index'])
        ->middleware('role:parent,teacher,super_teacher,system_admin,pta')
        ->name('school-calendar');
    Route::post('dashboard/message', [DashboardController::class, 'submitParentMessage'])
        ->middleware(['auth', 'role:parent'])
        ->name('dashboard.parent.message');

    Route::post('/calendar-events', [SchoolCalendarEventController::class, 'store'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('calendar-events.store');
    Route::patch('/calendar-events/{schoolCalendarEvent}', [SchoolCalendarEventController::class, 'update'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('calendar-events.update');
    Route::delete('/calendar-events/{schoolCalendarEvent}', [SchoolCalendarEventController::class, 'destroy'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('calendar-events.destroy');

    Route::get('/teacher/records', [TeacherRecordsController::class, 'index'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.records');
    Route::get('/teacher/records/family/{familyCode}', [TeacherRecordsController::class, 'familyDetail'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.records.family');
    Route::patch('/teacher/records/family/{familyCode}/parent-profile', [TeacherRecordsController::class, 'updateFamilyParentProfile'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.records.family.parent-profile.update');
    Route::patch('/teacher/records/students/{student}/tags', [TeacherRecordsController::class, 'updateStudentTags'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.records.students.tags.update');
    Route::get('/teacher/records/family/{familyCode}/payments/export', [TeacherRecordsController::class, 'exportFamilyPayments'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.records.family.payments.export');
    Route::get('/teacher/family-login-monitor', [TeacherFamilyLoginMonitorController::class, 'index'])
        ->middleware('role:teacher,super_teacher,system_admin')
        ->name('teacher.family-login-monitor');
    Route::post('/teacher/family-login-monitor/invite', [TeacherFamilyLoginMonitorController::class, 'sendInvite'])
        ->middleware('role:teacher,super_teacher,system_admin')
        ->name('teacher.family-login-monitor.invite.send');
    Route::get('/teacher/finance-accounting', [TeacherFinanceAccountingController::class, 'index'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.finance-accounting');
    Route::get('/teacher/class-progress', [TeacherClassProgressController::class, 'index'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.class-progress');
    Route::get('/teacher/finance-accounting/export', [TeacherFinanceAccountingController::class, 'export'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.finance-accounting.export');
    Route::get('/teacher/records/duplicates/{student}/review', [TeacherRecordsController::class, 'reviewDuplicate'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.records.duplicates.review');
    Route::delete('/teacher/records/duplicates/{student}', [TeacherRecordsController::class, 'destroyDuplicate'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.records.duplicates.destroy');

    Route::get('/pta/dashboard', [PtaDashboardController::class, 'index'])
        ->middleware('role:pta,teacher,super_teacher,system_admin')
        ->name('pta.dashboard');

    Route::get('/parent/dashboard', [ParentDashboardController::class, 'index'])
        ->middleware('role:parent')
        ->name('parent.dashboard');

    Route::post('/parent/search/select/{familyBilling}', [PublicParentSearchController::class, 'selectFamily'])
        ->middleware('role:parent')
        ->name('parent.search.select');

    Route::group(['prefix' => 'parent/payments', 'as' => 'parent.payments.'], function () {
        Route::get('history', [ParentPaymentController::class, 'history'])->name('history');
        Route::get('{familyBilling}/checkout', [ParentPaymentController::class, 'checkout'])->name('checkout');
        Route::post('{familyBilling}/create', [ParentPaymentController::class, 'create'])->name('create');
        Route::get('summary/{externalOrderId}', [ParentPaymentController::class, 'summary'])->name('summary');
        Route::get('receipt/{externalOrderId}', [ParentPaymentController::class, 'receiptPdf'])->name('receipt');
    });

    Route::get('/students/import', [StudentImportController::class, 'create'])
        ->middleware('role:system_admin')
        ->name('students.import.form');

    Route::get('/students/families', [StudentFamilyController::class, 'index'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('students.family.list');

    Route::post('/students/import', [StudentImportController::class, 'store'])
        ->middleware('role:system_admin')
        ->name('students.import');

    Route::get('/teacher/reconcile', [TeacherReconciliationController::class, 'index'])
        ->middleware('role:system_admin')
        ->name('teacher.reconcile.index');
    Route::post('/teacher/reconcile/preview', [TeacherReconciliationController::class, 'preview'])
        ->middleware('role:system_admin')
        ->name('teacher.reconcile.preview');
    Route::post('/teacher/reconcile/apply', [TeacherReconciliationController::class, 'apply'])
        ->middleware('role:system_admin')
        ->name('teacher.reconcile.apply');
    Route::get('/system/backups', [TeacherReconciliationController::class, 'backupIndex'])
        ->middleware('role:system_admin')
        ->name('system.backups.index');
    Route::post('/system/backups', [TeacherReconciliationController::class, 'createBackup'])
        ->middleware('role:system_admin')
        ->name('system.backups.create');
    Route::get('/system/backups/{fileName}', [TeacherReconciliationController::class, 'downloadBackup'])
        ->middleware('role:system_admin')
        ->name('system.backups.download');
    Route::delete('/system/backups/{fileName}', [TeacherReconciliationController::class, 'deleteBackup'])
        ->middleware('role:system_admin')
        ->name('system.backups.delete');

    Route::post('/billing/setup/current-year', [BillingSetupController::class, 'setupCurrentYear'])
        ->middleware('role:system_admin')
        ->name('billing.setup.current-year');

    Route::get('/system/portal-seo', [PortalSeoSettingsController::class, 'index'])
        ->middleware('role:system_admin')
        ->name('system.portal-seo.index');
    Route::patch('/system/portal-seo', [PortalSeoSettingsController::class, 'update'])
        ->middleware('role:system_admin')
        ->name('system.portal-seo.update');

    Route::get('/system/payment-testers', [PaymentTesterUserController::class, 'index'])
        ->middleware('role:system_admin')
        ->name('system.payment-testers.index');
    Route::get('/system/visitor-logs', [VisitorLogController::class, 'index'])
        ->middleware('role:system_admin')
        ->name('system.visitor-logs.index');
    Route::get('/system/visitor-logs/export', [VisitorLogController::class, 'export'])
        ->middleware('role:system_admin')
        ->name('system.visitor-logs.export');
    Route::get('/system/payment-funnel-monitor', [PaymentFunnelMonitorController::class, 'index'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('system.payment-funnel-monitor.index');
    Route::post('/system/payment-funnel-monitor/check-gateway', [PaymentFunnelMonitorController::class, 'checkGatewayStatus'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('system.payment-funnel-monitor.check-gateway');
    Route::patch('/system/payment-testers/{user}', [PaymentTesterUserController::class, 'update'])
        ->middleware('role:system_admin')
        ->name('system.payment-testers.update');
    Route::post('/system/payment-testers/whatsapp-test', [PaymentTesterUserController::class, 'sendWhatsappTest'])
        ->middleware('role:system_admin')
        ->name('system.payment-testers.whatsapp-test');
    Route::post('/system/payment-testers/payment-success-whatsapp-test', [PaymentTesterUserController::class, 'sendPaymentSuccessWhatsappTest'])
        ->middleware('role:system_admin')
        ->name('system.payment-testers.payment-success-whatsapp-test');
    Route::post('/system/payment-testers/parent-phone/reset', [PaymentTesterUserController::class, 'resetParentPhone'])
        ->middleware('role:system_admin')
        ->name('system.payment-testers.parent-phone.reset');
    Route::post('/system/payment-testers/parent-phone/correct', [PaymentTesterUserController::class, 'correctParentPhone'])
        ->middleware('role:system_admin')
        ->name('system.payment-testers.parent-phone.correct');
    Route::post('/system/payment-testers/portal-test-invite', [PaymentTesterUserController::class, 'createPortalTestInvite'])
        ->middleware('role:system_admin')
        ->name('system.payment-testers.portal-test-invite');

    Route::get('/super-teacher/teachers', [TeacherUserManagementController::class, 'index'])
        ->middleware('role:super_teacher,system_admin')
        ->name('super-teacher.teachers.index');
    Route::post('/super-teacher/teachers', [TeacherUserManagementController::class, 'store'])
        ->middleware('role:super_teacher,system_admin')
        ->name('super-teacher.teachers.store');
    Route::patch('/super-teacher/teachers/{user}', [TeacherUserManagementController::class, 'update'])
        ->middleware('role:super_teacher,system_admin')
        ->name('super-teacher.teachers.update');
    Route::patch('/super-teacher/teachers/{user}/status', [TeacherUserManagementController::class, 'updateStatus'])
        ->middleware('role:super_teacher,system_admin')
        ->name('super-teacher.teachers.update-status');
    Route::patch('/super-teacher/teachers/{user}/whatsapp-notifications', [TeacherUserManagementController::class, 'updateWhatsappNotifications'])
        ->middleware('role:super_teacher,system_admin')
        ->name('super-teacher.teachers.update-whatsapp-notifications');
    Route::delete('/super-teacher/teachers/{user}', [TeacherUserManagementController::class, 'destroy'])
        ->middleware('role:super_teacher,system_admin')
        ->name('super-teacher.teachers.destroy');
});

require __DIR__.'/settings.php';
