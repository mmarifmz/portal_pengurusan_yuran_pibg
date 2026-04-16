<?php

use App\Http\Controllers\Api\ParentTacNotificationController;
use App\Http\Controllers\Api\PaymentReceiptNotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/notifications/parent-tac', ParentTacNotificationController::class)
    ->name('api.notifications.parent-tac');

Route::post('/transactions/{transaction}/notify-receipt', PaymentReceiptNotificationController::class)
    ->name('api.transactions.notify-receipt');
