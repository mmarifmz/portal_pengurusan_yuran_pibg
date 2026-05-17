<?php

namespace App\Http\Controllers;

use App\Models\PaymentGatewaySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PaymentGatewaySettingController extends Controller
{
    public function index(): View
    {
        return view('system.payment-gateway-settings', [
            'paymentGatewaySetting' => PaymentGatewaySetting::current(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return back()
                ->withErrors([
                    'enable_fpx' => 'Tetapan Payment Gateway belum tersedia. Sila jalankan migration terlebih dahulu.',
                ])
                ->withInput();
        }

        $validated = $request->validate([
            'enable_fpx' => ['nullable', 'boolean'],
            'enable_duitnow_qr' => ['nullable', 'boolean'],
        ]);

        $enableFpx = (bool) ($validated['enable_fpx'] ?? false);
        $enableDuitNowQr = (bool) ($validated['enable_duitnow_qr'] ?? false);

        if (! $enableFpx && ! $enableDuitNowQr) {
            return back()
                ->withErrors([
                    'enable_fpx' => 'Sila aktifkan sekurang-kurangnya satu kaedah pembayaran.',
                ])
                ->withInput();
        }

        $setting = PaymentGatewaySetting::current();
        $setting->fill([
            'enable_fpx' => $enableFpx,
            'enable_duitnow_qr' => $enableDuitNowQr,
            'charge_duitnow_qr_to_customer' => true,
            'updated_by' => $request->user()?->id,
        ])->save();

        return redirect()
            ->route('system.payment-gateway-settings.index')
            ->with('status', 'Tetapan Payment Gateway berjaya dikemaskini.');
    }
}
