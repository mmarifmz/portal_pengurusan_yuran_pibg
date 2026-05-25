<?php

use App\Http\Controllers\Api\ParentTacNotificationController;
use App\Http\Controllers\Api\PaymentReceiptNotificationController;
use App\Http\Controllers\Api\PaymentStatusSearchController;
use Illuminate\Support\Facades\Route;

Route::post('/notifications/parent-tac', ParentTacNotificationController::class)
    ->name('api.notifications.parent-tac');

Route::post('/transactions/{transaction}/notify-receipt', PaymentReceiptNotificationController::class)
    ->name('api.transactions.notify-receipt');

Route::get('/v1/payment-status/search', PaymentStatusSearchController::class)
    ->middleware('teacher.api.key')
    ->name('api.v1.payment-status.search');
