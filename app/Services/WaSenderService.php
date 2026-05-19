<?php

namespace App\Services;

use App\Exceptions\WaSenderRateLimitException;
use App\Support\MalaysianPhone;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WaSenderService
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function sendText(string $phone, string $message): array
    {
        $apiKey = (string) config('services.wasender.api_key');
        $baseUrl = (string) config('services.wasender.api_url', config('services.wasender.base_url', 'https://www.wasenderapi.com/api'));

        if ($apiKey === '') {
            throw new RuntimeException('Missing Wasender API configuration.');
        }

        $normalizedPhone = MalaysianPhone::normalize($phone);
        if ($normalizedPhone === null) {
            throw new RuntimeException('Invalid Malaysian WhatsApp number.');
        }

        $response = $this->http
            ->asJson()
            ->acceptJson()
            ->withToken($apiKey)
            ->baseUrl(rtrim($baseUrl, '/'))
            ->post('/send-message', [
                'to' => $normalizedPhone,
                'text' => $message,
            ]);

        $payload = $response->json();
        $headers = [
            'x-ratelimit-limit' => (string) $response->header('X-RateLimit-Limit', ''),
            'x-ratelimit-remaining' => (string) $response->header('X-RateLimit-Remaining', ''),
            'x-ratelimit-reset' => (string) $response->header('X-RateLimit-Reset', ''),
        ];

        Log::info('WaSender API response received.', [
            'phone' => $normalizedPhone,
            'status_code' => $response->status(),
            'successful' => $response->successful(),
            'provider_status' => Arr::get(is_array($payload) ? $payload : [], 'data.status'),
            'rate_limit_headers' => $headers,
        ]);

        if (! $response->successful()) {
            $retryAfter = $this->resolveRetryAfterSeconds($response->status(), is_array($payload) ? $payload : [], $headers);

            if ($retryAfter !== null) {
                throw new WaSenderRateLimitException(
                    'Wasender rate limit hit.',
                    $retryAfter,
                    is_array($payload) ? $payload : ['raw' => $response->body()],
                    $headers,
                );
            }

            throw new RuntimeException('Wasender WhatsApp send failed: '.$response->body());
        }

        return [
            'provider' => 'wasender',
            'status' => Arr::get($payload, 'data.status', 'queued'),
            'message_id' => Arr::get($payload, 'data.msgId'),
            'jid' => Arr::get($payload, 'data.jid'),
            'headers' => $headers,
            'response' => is_array($payload) ? $payload : ['raw' => $response->body()],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function resolveRetryAfterSeconds(int $statusCode, array $payload, array $headers): ?int
    {
        $message = mb_strtolower((string) ($payload['message'] ?? ''));
        $retryAfter = $payload['retry_after'] ?? null;

        if (is_numeric($retryAfter)) {
            return max(1, (int) ceil((float) $retryAfter));
        }

        if ($statusCode === 429 || str_contains($message, 'account protection') || str_contains($message, 'rate limit')) {
            $resetHeader = $headers['x-ratelimit-reset'] ?? '';

            if (is_numeric($resetHeader)) {
                return max(1, (int) ceil((float) $resetHeader));
            }

            return 5;
        }

        return null;
    }
}
