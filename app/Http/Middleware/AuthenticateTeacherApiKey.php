<?php

namespace App\Http\Middleware;

use App\Models\ApiAccessLog;
use App\Models\TeacherApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTeacherApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $plainKey = trim((string) $request->bearerToken());

        if ($plainKey === '' || ! str_starts_with($plainKey, 'pibg_live_')) {
            return $this->reject($request, $startedAt, 401, 'Invalid API key.');
        }

        $apiKey = TeacherApiKey::query()
            ->with('teacher')
            ->where('key_hash', TeacherApiKey::hashPlainKey($plainKey))
            ->first();

        if (! $apiKey) {
            return $this->reject($request, $startedAt, 401, 'Invalid API key.');
        }

        if (! $apiKey->isActive()) {
            return $this->reject($request, $startedAt, 403, 'API key revoked.', $apiKey);
        }

        $rateKey = 'teacher-api-key:'.$apiKey->id;

        if (RateLimiter::tooManyAttempts($rateKey, 60)) {
            return $this->reject($request, $startedAt, 429, 'Too many requests. Please try again later.', $apiKey);
        }

        RateLimiter::hit($rateKey, 60);

        $apiKey->increment('total_calls');
        $apiKey->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('teacher_api_key', $apiKey);
        $request->attributes->set('api_teacher', $apiKey->teacher);

        $response = $next($request);

        $this->logRequest(
            request: $request,
            startedAt: $startedAt,
            status: $response->getStatusCode(),
            apiKey: $apiKey,
            resultCount: (int) $request->attributes->get('api_result_count', 0),
            errorMessage: $request->attributes->get('api_error_message')
        );

        return $response;
    }

    private function reject(
        Request $request,
        float $startedAt,
        int $status,
        string $message,
        ?TeacherApiKey $apiKey = null
    ): JsonResponse {
        $this->logRequest($request, $startedAt, $status, $apiKey, 0, $message);

        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    private function logRequest(
        Request $request,
        float $startedAt,
        int $status,
        ?TeacherApiKey $apiKey = null,
        int $resultCount = 0,
        ?string $errorMessage = null
    ): void {
        ApiAccessLog::query()->create([
            'teacher_id' => $apiKey?->teacher_id,
            'api_key_id' => $apiKey?->id,
            'endpoint' => '/'.$request->path(),
            'method' => $request->method(),
            'query_text' => trim((string) $request->query('q')) ?: null,
            'request_ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'response_status' => $status,
            'result_count' => max(0, $resultCount),
            'error_message' => $errorMessage,
            'execution_time_ms' => max(0, (int) round((microtime(true) - $startedAt) * 1000)),
        ]);
    }
}
