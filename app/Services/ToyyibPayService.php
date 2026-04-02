<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ToyyibPayService
{
    public function createBill(array $payload): string
    {
        $response = Http::asForm()
            ->timeout(20)
            ->post($this->endpoint('/index.php/api/createBill'), array_merge([
                'userSecretKey' => (string) config('services.toyyibpay.user_secret_key'),
                'categoryCode' => (string) config('services.toyyibpay.category_code'),
            ], $payload));

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create ToyyibPay bill: '.$response->body());
        }

        $data = $response->json();
        $billCode = $data[0]['BillCode'] ?? null;

        if (! is_string($billCode) || blank($billCode)) {
            throw new RuntimeException('ToyyibPay did not return BillCode.');
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
