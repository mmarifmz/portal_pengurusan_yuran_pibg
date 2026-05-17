<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PaymentGatewaySetting extends Model
{
    protected $fillable = [
        'enable_fpx',
        'enable_duitnow_qr',
        'charge_duitnow_qr_to_customer',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enable_fpx' => 'boolean',
            'enable_duitnow_qr' => 'boolean',
            'charge_duitnow_qr_to_customer' => 'boolean',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toToyyibPayPayload(): array
    {
        $payload = [];

        if ($this->enable_fpx) {
            $payload['billPaymentChannel'] = '0';
        }

        if ($this->enable_duitnow_qr) {
            $payload['enableDuitNowQR'] = '1';
            $payload['chargeDuitNowQR'] = '1';
        }

        return $payload;
    }

    public function parentPaymentNotice(): string
    {
        if ($this->enable_duitnow_qr && $this->enable_fpx) {
            return 'Pembayaran boleh dibuat melalui FPX / Internet Banking atau DuitNow QR. Caj perkhidmatan hanya terpakai untuk DuitNow QR sahaja: 1% atau minimum RM1.00, yang mana lebih tinggi. Caj ini akan dipaparkan di halaman ToyyibPay sebelum bayaran disahkan.';
        }

        if ($this->enable_duitnow_qr) {
            return 'Pembayaran akan diteruskan melalui DuitNow QR. Caj perkhidmatan DuitNow QR ialah 1% atau minimum RM1.00, yang mana lebih tinggi. Caj ini akan dipaparkan di halaman ToyyibPay sebelum bayaran disahkan.';
        }

        return 'Pembayaran akan diteruskan melalui FPX / Internet Banking di halaman ToyyibPay.';
    }

    public function qrServiceFeeNotice(): string
    {
        return 'Caj perkhidmatan DuitNow QR akan ditanggung oleh pembayar.';
    }

    public static function current(): self
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return new self([
                'enable_fpx' => true,
                'enable_duitnow_qr' => false,
                'charge_duitnow_qr_to_customer' => true,
            ]);
        }

        $existing = self::query()->orderBy('id')->first();

        if ($existing) {
            return $existing;
        }

        return self::query()->create([
            'enable_fpx' => true,
            'enable_duitnow_qr' => false,
            'charge_duitnow_qr_to_customer' => true,
        ]);
    }
}
