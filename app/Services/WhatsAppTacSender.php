<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppTacSender
{
    public function sendTac(string $phone, string $code): void
    {
        if (! config('services.whatsapp.enabled')) {
            return;
        }

        // Keep feature tests deterministic.
        if (app()->environment('testing')) {
            return;
        }

        $provider = (string) config('services.whatsapp.provider', 'twilio');

        if ($provider !== 'twilio') {
            throw new RuntimeException('Unsupported WhatsApp provider configured.');
        }

        $this->sendViaTwilio($phone, $code);
    }

    private function sendViaTwilio(string $phone, string $code): void
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
                'Body' => "TAC PIBG anda: {$code}. Kod ini sah selama 5 minit.",
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Twilio WhatsApp send failed: '.$response->body());
        }
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