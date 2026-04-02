<?php

namespace App\Http\Controllers;

use App\Models\ParentLoginOtp;
use App\Models\User;
use App\Services\WhatsAppTacSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ParentOtpAuthController extends Controller
{
    public function __construct(private readonly WhatsAppTacSender $whatsAppTacSender)
    {
    }

    public function showRequestForm(): View
    {
        return view('parent.auth.request-pin');
    }

    public function sendTac(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
        ]);

        $phone = $this->normalizePhone($validated['phone']);

        $parent = User::query()
            ->where('role', 'parent')
            ->where('phone', $phone)
            ->first();

        if (! $parent) {
            return back()->withErrors([
                'phone' => 'Phone number not found in parent records.',
            ])->withInput();
        }

        ParentLoginOtp::query()
            ->where('phone', $phone)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = (string) random_int(100000, 999999);

        $otp = ParentLoginOtp::query()->create([
            'user_id' => $parent->id,
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'channel' => 'whatsapp',
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        try {
            $this->dispatchTac($parent, $code);
        } catch (\Throwable $exception) {
            $otp->update(['used_at' => now()]);

            Log::error('Unable to dispatch parent TAC.', [
                'phone' => $phone,
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'phone' => 'Unable to send TAC right now. Please verify WhatsApp setup and try again.',
            ])->withInput();
        }

        session([
            'parent_otp_phone' => $phone,
        ]);

        if (app()->environment('testing') || config('services.whatsapp.debug_show_tac')) {
            session(['parent_otp_debug_code' => $code]);
        }

        return redirect()->route('parent.login.verify.form')
            ->with('status', 'TAC sent. Check your WhatsApp.');
    }

    public function showVerifyForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('parent_otp_phone')) {
            return redirect()->route('parent.login.form');
        }

        return view('parent.auth.verify-pin', [
            'phone' => $request->session()->get('parent_otp_phone'),
            'debugCode' => app()->environment('testing') || config('services.whatsapp.debug_show_tac')
                ? $request->session()->get('parent_otp_debug_code')
                : null,
        ]);
    }

    public function verifyTac(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits:6'],
        ]);

        $phone = (string) $request->session()->get('parent_otp_phone');

        if (! filled($phone)) {
            return redirect()->route('parent.login.form');
        }

        $otp = ParentLoginOtp::query()
            ->where('phone', $phone)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();

        if (! $otp) {
            return back()->withErrors([
                'pin' => 'TAC expired. Please request a new TAC.',
            ]);
        }

        $otp->increment('attempts');

        if (! Hash::check($validated['pin'], $otp->code_hash)) {
            if ($otp->attempts >= 5) {
                $otp->update(['used_at' => now()]);
            }

            return back()->withErrors([
                'pin' => 'Invalid TAC pin.',
            ]);
        }

        $otp->update(['used_at' => now()]);

        $user = User::query()
            ->where('id', $otp->user_id)
            ->where('role', 'parent')
            ->first();

        if (! $user) {
            return back()->withErrors([
                'pin' => 'Unable to authenticate this parent account.',
            ]);
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->forget(['parent_otp_phone', 'parent_otp_debug_code']);

        return redirect()->route('parent.dashboard');
    }

    private function dispatchTac(User $parent, string $code): void
    {
        $this->whatsAppTacSender->sendTac((string) $parent->phone, $code);

        if (blank($parent->email)) {
            return;
        }

        try {
            Mail::raw("Your PIBG TAC code is {$code}. It expires in 5 minutes.", function ($message) use ($parent) {
                $message->to($parent->email)
                    ->subject('PIBG Parent TAC Code');
            });
        } catch (\Throwable $exception) {
            Log::warning('Unable to send TAC email backup.', [
                'email' => $parent->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\s+/', '', $phone) ?: $phone;
    }
}