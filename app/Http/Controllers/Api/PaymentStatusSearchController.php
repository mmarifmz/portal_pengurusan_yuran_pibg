<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentStatusApiSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentStatusSearchController extends Controller
{
    public function __invoke(Request $request, PaymentStatusApiSearchService $searchService): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'q' => ['required', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'class' => ['nullable', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first('q') ?: 'Invalid search request.';
            $request->attributes->set('api_error_message', $message);

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $query = trim((string) $validated['q']);
        $billingYear = (int) ($validated['year'] ?? now()->year);
        $className = trim((string) ($validated['class'] ?? '')) ?: null;

        $rows = $searchService->search($query, $billingYear, $className);
        $request->attributes->set('api_result_count', $rows->count());

        return response()->json([
            'success' => true,
            'query' => $query,
            'count' => $rows->count(),
            'data' => $rows->values(),
        ]);
    }
}
