<?php

use App\Http\Controllers\BillingSetupController;
use App\Http\Controllers\ParentDashboardController;
use App\Http\Controllers\ParentOtpAuthController;
use App\Http\Controllers\PtaDashboardController;
use App\Http\Controllers\PublicParentSearchController;
use App\Http\Controllers\TeacherDashboardController;
use App\Http\Controllers\ParentPaymentController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/parent/search', [PublicParentSearchController::class, 'index'])
    ->name('parent.search');

Route::middleware('guest')->prefix('parent/login')->name('parent.login.')->group(function () {
    Route::get('/', [ParentOtpAuthController::class, 'showRequestForm'])->name('form');
    Route::post('/request', [ParentOtpAuthController::class, 'sendTac'])->name('request');
    Route::get('/verify', [ParentOtpAuthController::class, 'showVerifyForm'])->name('verify.form');
    Route::post('/verify', [ParentOtpAuthController::class, 'verifyTac'])->name('verify.submit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('/teacher/dashboard', [TeacherDashboardController::class, 'index'])
        ->middleware('role:teacher,pta')
        ->name('teacher.dashboard');

    Route::get('/pta/dashboard', [PtaDashboardController::class, 'index'])
        ->middleware('role:pta,teacher')
        ->name('pta.dashboard');

    Route::get('/parent/dashboard', [ParentDashboardController::class, 'index'])
        ->middleware('role:parent')
        ->name('parent.dashboard');
    Route::group(['prefix' => 'parent/payments', 'as' => 'parent.payments.'], function () {
        Route::get('{familyBilling}/checkout', [ParentPaymentController::class, 'checkout'])->name('checkout');
        Route::post('{familyBilling}/create', [ParentPaymentController::class, 'create'])->name('create');
        Route::get('summary/{externalOrderId}', [ParentPaymentController::class, 'summary'])->name('summary');
        Route::get('receipt/{externalOrderId}', [ParentPaymentController::class, 'receiptPdf'])->name('receipt');
        Route::get('summary/return', [ParentPaymentController::class, 'handleReturn'])->name('summary.return');
        Route::post('callback', [ParentPaymentController::class, 'handleCallback'])->name('toyyibpay.callback');
    });

    Route::post('/billing/setup/current-year', [BillingSetupController::class, 'setupCurrentYear'])
        ->middleware('role:teacher,pta')
        ->name('billing.setup.current-year');
});

require __DIR__.'/settings.php';
