<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use RuntimeException;

class WhatsAppTacSender
{
    public function sendTac(string $phone, string $code, ?string $familyCode = null): array
    {
        $familyCodeText = filled($familyCode) ? $familyCode : 'SSP-XXXX';

        return $this->sendMessage(
            $phone,
            "SSP PIBG portal TAC : {$code}\n"
            ."Requested for family code {$familyCodeText}\n"
            ."-----\n"
            ."Kod ini sah selama 5 minit.\n"
            ."Source : https://yuranpibg.sripetaling.edu.my/"
        );
    }

    public function sendMessage(string $phone, string $message): array
    {
        if (! config('services.whatsapp.enabled')) {
            return [
                'provider' => 'wasender',
                'enabled' => false,
                'status' => 'skipped',
                'message' => 'WhatsApp notifications are disabled.',
            ];
        }

        if (app()->environment('testing')) {
            return [
                'provider' => 'wasender',
                'enabled' => true,
                'status' => 'testing',
            ];
        }

        $provider = (string) config('services.whatsapp.provider', 'wasender');

        return match ($provider) {
            'twilio' => $this->sendViaTwilio($phone, $message),
            'wasender' => $this->sendViaWasender($phone, $message),
            default => throw new RuntimeException('Unsupported WhatsApp provider configured.'),
        };
    }

    private function sendViaTwilio(string $phone, string $message): array
    {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $from = (string) config('services.whatsapp.twilio_from');

        if (blank($sid) || blank($token) || blank($from)) {
            throw new RuntimeException('Missing Twilio WhatsApp configuration.');
        }

        $toPhone = $this->normalizePhoneToE164($phone);

        $response = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => 'whatsapp:'.$toPhone,
                'Body' => $message,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Twilio WhatsApp send failed: '.$response->body());
        }

        return [
            'provider' => 'twilio',
            'enabled' => true,
            'status' => $response->json('status'),
            'message_id' => $response->json('sid'),
            'response' => $response->json(),
        ];
    }

    private function sendViaWasender(string $phone, string $message): array
    {
        $apiKey = (string) config('services.wasender.api_key');
        $baseUrl = (string) config('services.wasender.base_url', 'https://www.wasenderapi.com/api');
        $maxRetries = max(0, (int) config('services.wasender.retry_attempts', 2));
        $maxRetryDelaySeconds = max(1, (int) config('services.wasender.max_retry_delay_seconds', 10));

        if (blank($apiKey)) {
            throw new RuntimeException('Missing Wasender API configuration.');
        }

        $attempt = 0;

        do {
            $response = Http::asJson()
                ->acceptJson()
                ->withToken($apiKey)
                ->baseUrl($baseUrl)
                ->post('/send-message', [
                    'to' => $this->normalizePhoneToE164($phone),
                    'text' => $message,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'provider' => 'wasender',
                    'enabled' => true,
                    'status' => Arr::get($data, 'data.status', 'queued'),
                    'message_id' => Arr::get($data, 'data.msgId'),
                    'jid' => Arr::get($data, 'data.jid'),
                    'response' => $data,
                ];
            }

            $retryAfterSeconds = $this->extractWasenderRetryAfter($response->json(), $response->body());
            $shouldRetry = $retryAfterSeconds !== null && $attempt < $maxRetries;

            if (! $shouldRetry) {
                throw new RuntimeException('Wasender WhatsApp send failed: '.$response->body());
            }

            sleep(min($maxRetryDelaySeconds, max(1, $retryAfterSeconds)));
            $attempt++;
        } while (true);
    }

    private function extractWasenderRetryAfter(mixed $json, string $body): ?int
    {
        $retryAfter = Arr::get(is_array($json) ? $json : [], 'retry_after');

        if (is_numeric($retryAfter)) {
            $value = (int) $retryAfter;

            return $value > 0 ? $value : null;
        }

        if (preg_match('/"retry_after"\s*:\s*(\d+)/', $body, $matches) === 1) {
            $value = (int) ($matches[1] ?? 0);

            return $value > 0 ? $value : null;
        }

        return null;
    }

    private function normalizePhoneToE164(string $phone): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return '+'.preg_replace('/\D+/', '', substr($phone, 1));
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '60')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+60'.substr($digits, 1);
        }

        return '+'.$digits;
    }
}
