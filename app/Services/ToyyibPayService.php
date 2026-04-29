<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ToyyibPayService
{
    public function createBill(array $payload): string
    {
        $userSecretKey = (string) config('services.toyyibpay.user_secret_key');
        $categoryCode = (string) config('services.toyyibpay.category_code');

        if (blank($userSecretKey) || blank($categoryCode)) {
            throw new RuntimeException('ToyyibPay configuration is missing. Please set TOYYIBPAY_USER_SECRET_KEY and TOYYIBPAY_CATEGORY_CODE.');
        }

        $response = Http::asForm()
            ->timeout(20)
            ->post($this->endpoint('/index.php/api/createBill'), array_merge([
                'userSecretKey' => $userSecretKey,
                'categoryCode' => $categoryCode,
            ], $payload));

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create ToyyibPay bill: '.$response->body());
        }

        $data = $response->json();
        $billCode = $data[0]['BillCode']
            ?? $data[0]['billCode']
            ?? $data['BillCode']
            ?? $data['billCode']
            ?? null;

        if (! is_string($billCode) || blank($billCode)) {
            $message = $data[0]['msg']
                ?? $data[0]['message']
                ?? $data['msg']
                ?? $data['message']
                ?? 'ToyyibPay did not return BillCode.';

            throw new RuntimeException('ToyyibPay bill creation failed: '.(string) $message);
        }

        return $billCode;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBillTransactions(string $billCode): array
    {
        $response = Http::asForm()
            ->timeout(20)
            ->post($this->endpoint('/index.php/api/getBillTransactions'), [
                'billCode' => $billCode,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to retrieve ToyyibPay transaction status.');
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivateBill(string $billCode): array
    {
        $secretKey = (string) config('services.toyyibpay.user_secret_key');

        if (blank($secretKey)) {
            throw new RuntimeException('ToyyibPay configuration is missing. Please set TOYYIBPAY_USER_SECRET_KEY.');
        }

        $response = Http::asForm()
            ->timeout(20)
            ->post($this->endpoint('/index.php/api/inactiveBill'), [
                'secretKey' => $secretKey,
                'billCode' => $billCode,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to deactivate ToyyibPay bill: '.$response->body());
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    public function verifyCallbackHash(string $status, string $orderId, string $refNo, string $receivedHash): bool
    {
        $secret = (string) config('services.toyyibpay.user_secret_key');
        $expectedHash = md5($secret.$status.$orderId.$refNo.'ok');

        return hash_equals($expectedHash, $receivedHash);
    }

    public function paymentUrl(string $billCode): string
    {
        return rtrim((string) config('services.toyyibpay.base_url', 'https://toyyibpay.com'), '/').'/'.$billCode;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.toyyibpay.base_url', 'https://toyyibpay.com'), '/').$path;
    }
}
