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
use App\Http\Controllers\PaymentTesterUserController;
use App\Http\Controllers\PortalSeoSettingsController;
use App\Http\Controllers\DashboardController;
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

    return view('welcome', [
        'classOptions' => $classOptions,
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

// ToyyibPay return/callback must stay public (gateway/browser call outside auth middleware).
Route::get('/parent/payments/summary/return', [ParentPaymentController::class, 'handleReturn'])
    ->name('parent.payments.summary.return');
Route::post('/parent/payments/callback', [ParentPaymentController::class, 'handleCallback'])
    ->name('parent.payments.toyyibpay.callback');

// Legacy aliases for older bill configurations.
Route::get('/payment-return', [ParentPaymentController::class, 'handleReturn']);
Route::post('/payment-webhook', [ParentPaymentController::class, 'handleCallback']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
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
    Route::get('/teacher/records/family/{familyCode}/payments/export', [TeacherRecordsController::class, 'exportFamilyPayments'])
        ->middleware('role:teacher,super_teacher,system_admin,pta')
        ->name('teacher.records.family.payments.export');
    Route::get('/teacher/family-login-monitor', [TeacherFamilyLoginMonitorController::class, 'index'])
        ->middleware('role:teacher,super_teacher,system_admin')
        ->name('teacher.family-login-monitor');
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
    Route::post('/teacher/reconcile/backup', [TeacherReconciliationController::class, 'createBackup'])
        ->middleware('role:system_admin')
        ->name('teacher.reconcile.backup.create');
    Route::get('/teacher/reconcile/backup/{fileName}', [TeacherReconciliationController::class, 'downloadBackup'])
        ->middleware('role:system_admin')
        ->name('teacher.reconcile.backup.download');
    Route::delete('/teacher/reconcile/backup/{fileName}', [TeacherReconciliationController::class, 'deleteBackup'])
        ->middleware('role:system_admin')
        ->name('teacher.reconcile.backup.delete');

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
    Route::patch('/system/payment-testers/{user}', [PaymentTesterUserController::class, 'update'])
        ->middleware('role:system_admin')
        ->name('system.payment-testers.update');
    Route::post('/system/payment-testers/whatsapp-test', [PaymentTesterUserController::class, 'sendWhatsappTest'])
        ->middleware('role:system_admin')
        ->name('system.payment-testers.whatsapp-test');

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
