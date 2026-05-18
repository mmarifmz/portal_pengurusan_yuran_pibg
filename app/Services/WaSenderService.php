<?php

namespace App\Services;

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

        Log::info('WaSender API response received.', [
            'phone' => $normalizedPhone,
            'status_code' => $response->status(),
            'successful' => $response->successful(),
            'provider_status' => Arr::get(is_array($payload) ? $payload : [], 'data.status'),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Wasender WhatsApp send failed: '.$response->body());
        }

        return [
            'provider' => 'wasender',
            'status' => Arr::get($payload, 'data.status', 'queued'),
            'message_id' => Arr::get($payload, 'data.msgId'),
            'jid' => Arr::get($payload, 'data.jid'),
            'response' => is_array($payload) ? $payload : ['raw' => $response->body()],
        ];
    }
}
