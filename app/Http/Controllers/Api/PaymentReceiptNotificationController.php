<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyPaymentTransaction;
use App\Services\ParentPaymentNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentReceiptNotificationController extends Controller
{
    public function __invoke(
        Request $request,
        FamilyPaymentTransaction $transaction,
        ParentPaymentNotificationService $paymentNotificationService
    ): JsonResponse {
        $validated = $request->validate([
            'parent_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:25'],
        ]);

        $delivery = $paymentNotificationService->sendPaymentReceipt(
            $transaction,
            $validated['phone'] ?? null,
            $validated['parent_name'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'transaction_id' => $transaction->id,
                'receipt_url' => $paymentNotificationService->receiptUrl($transaction->fresh()),
                'delivery' => $delivery,
            ],
        ]);
    }
}
