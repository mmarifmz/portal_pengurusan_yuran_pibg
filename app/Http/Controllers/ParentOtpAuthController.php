<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use App\Models\User;
use App\Services\WhatsAppTacSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ParentOtpAuthController extends Controller
{
    public function __construct(private readonly WhatsAppTacSender $whatsAppTacSender)
    {
    }

    public function showRequestForm(Request $request): View
    {
        $selectedBilling = null;
        $billingId = $request->query('billing');

        if (filled($billingId)) {
            $selectedBilling = FamilyBilling::query()->find($billingId);
        }

        return view('parent.auth.request-pin', [
            'prefillPhone' => (string) $request->query('phone', ''),
            'selectedBilling' => $selectedBilling,
            // Temporarily hidden while we redesign tukar nombor telefon flow.
            'showPaidFamilyPhoneReset' => false,
            'paidFamilyResetPhone' => (string) $request->session()->get('paid_family_phone_reset_phone', ''),
        ]);
    }

    public function sendTac(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'family_billing_id' => ['nullable', 'integer', 'exists:family_billings,id'],
            'confirm_phone_reset' => ['nullable', 'boolean'],
        ]);

        $phone = $this->normalizePhone($validated['phone']);
        $confirmPhoneReset = (bool) ($validated['confirm_phone_reset'] ?? false);
        $isTesterPhone = $this->isTesterPhone($phone);

        $selectedBilling = null;
        $parent = null;

        if (filled($validated['family_billing_id'] ?? null)) {
            $selectedBilling = FamilyBilling::query()->find($validated['family_billing_id']);

            if (! $selectedBilling) {
                return back()->withErrors([
                    'phone' => 'Unable to start payment onboarding for the selected family.',
                ])->withInput();
            }
        }

        if ($selectedBilling && ! $isTesterPhone && ! $this->phoneCanAccessFamilyBilling($phone, $selectedBilling)) {
            if ($confirmPhoneReset && $this->familyBillingAllowsPhoneReset($selectedBilling)) {
                $parent = $this->resetPaidFamilyPhone($phone, $selectedBilling);
            } else {
                return back()->withErrors([
                    'phone' => $this->familyBillingAllowsPhoneReset($selectedBilling)
                        ? 'Phone number does not match this family billing record yet. Please contact admin for phone update.'
                        : 'Phone number does not match this family billing record.',
                ])->withInput();
            }
        }

        $parent ??= User::query()
            ->where('role', 'parent')
            ->where('phone', $phone)
            ->first();

        if (! $parent && $isTesterPhone) {
            $parent = $this->findOrCreateTesterParent($phone);
        }

        if (! $parent && $selectedBilling) {
            $parent = $this->registerParentForFamily($phone, $selectedBilling);
        }

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
            $this->dispatchTac($parent, $code, $selectedBilling);
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

        if ($selectedBilling) {
            session([
                'parent_login_intended_checkout' => $selectedBilling->id,
            ]);
        } else {
            $request->session()->forget('parent_login_intended_checkout');
        }

        if (app()->environment('testing') || config('services.whatsapp.debug_show_tac')) {
            session(['parent_otp_debug_code' => $code]);
        }

        return redirect()->route('parent.login.verify.form')
            ->with('status', $confirmPhoneReset
                ? 'Family phone number updated. TAC sent. Check your WhatsApp.'
                : 'TAC sent. Check your WhatsApp.');
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
        $intendedCheckoutId = $request->session()->pull('parent_login_intended_checkout');
        $request->session()->forget(['parent_otp_phone', 'parent_otp_debug_code']);

        if ($intendedCheckoutId) {
            $familyBilling = FamilyBilling::query()->find($intendedCheckoutId);

            if ($familyBilling && ($this->userCanAccessFamilyBilling($user, $familyBilling) || $user->isParentTester())) {
                return redirect()->route('parent.payments.checkout', $familyBilling);
            }
        }

        return redirect()->route('parent.dashboard');
    }

    private function dispatchTac(User $parent, string $code, ?FamilyBilling $selectedBilling = null): void
    {
        $familyCode = $selectedBilling?->family_code
            ?? Student::query()
                ->where('parent_phone', (string) $parent->phone)
                ->whereNotNull('family_code')
                ->where('family_code', '!=', '')
                ->orderBy('family_code')
                ->value('family_code');

        $this->whatsAppTacSender->sendTac((string) $parent->phone, $code, $familyCode);

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

    private function phoneCanAccessFamilyBilling(string $phone, FamilyBilling $familyBilling): bool
    {
        return Student::query()
            ->where('parent_phone', $phone)
            ->where('family_code', $familyBilling->family_code)
            ->exists();
    }

    private function userCanAccessFamilyBilling(User $user, FamilyBilling $familyBilling): bool
    {
        return $this->phoneCanAccessFamilyBilling((string) $user->phone, $familyBilling);
    }

    private function familyBillingAllowsPhoneReset(FamilyBilling $familyBilling): bool
    {
        return $familyBilling->outstanding_amount <= 0
            || strtolower((string) $familyBilling->status) === 'paid';
    }

    private function resetPaidFamilyPhone(string $phone, FamilyBilling $familyBilling): User
    {
        $familyStudents = Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->get();

        $existingPhones = $familyStudents
            ->pluck('parent_phone')
            ->filter()
            ->unique()
            ->values();

        $parent = User::query()
            ->where('role', 'parent')
            ->where('phone', $phone)
            ->first();

        if (! $parent && $existingPhones->isNotEmpty()) {
            $parent = User::query()
                ->where('role', 'parent')
                ->whereIn('phone', $existingPhones)
                ->orderBy('id')
                ->first();
        }

        if ($parent) {
            $parent->forceFill([
                'phone' => $phone,
            ])->save();
        } else {
            $parent = $this->registerParentForFamily($phone, $familyBilling);
        }

        Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->update([
                'parent_phone' => $phone,
            ]);

        return $parent;
    }

    private function registerParentForFamily(string $phone, FamilyBilling $familyBilling): User
    {
        $familyStudents = Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        $parentName = (string) ($familyStudents->firstWhere('parent_name')?->parent_name
            ?? $familyStudents->first()?->parent_name
            ?? "Parent {$familyBilling->family_code}");

        $parent = User::query()->create([
            'name' => $parentName,
            'email' => sprintf(
                'parent-%s-%s@placeholder.local',
                Str::lower($familyBilling->family_code),
                preg_replace('/\D+/', '', $phone) ?: Str::lower((string) Str::ulid())
            ),
            'phone' => $phone,
            'role' => 'parent',
            'password' => Str::random(40),
            'email_verified_at' => now(),
        ]);

        Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->update([
                'parent_phone' => $phone,
            ]);

        return $parent;
    }

    private function isTesterPhone(string $phone): bool
    {
        $normalizedInput = $this->normalizePhoneForMatch($phone);

        if ($normalizedInput === '') {
            return false;
        }

        $testerPhones = collect((array) config('services.parent_tester_phones', []))
            ->map(fn ($testerPhone) => $this->normalizePhoneForMatch((string) $testerPhone))
            ->filter()
            ->values();

        return $testerPhones->contains($normalizedInput);
    }

    private function normalizePhoneForMatch(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '60')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '6'.$digits;
        }

        if (str_starts_with($digits, '1')) {
            return '60'.$digits;
        }

        return $digits;
    }

    private function findOrCreateTesterParent(string $phone): User
    {
        $existing = User::query()
            ->where('role', 'parent')
            ->where('phone', $phone)
            ->first();

        if ($existing) {
            return $existing;
        }

        $digits = $this->normalizePhoneForMatch($phone);

        return User::query()->create([
            'name' => 'Treasury Tester',
            'email' => sprintf('tester-%s@placeholder.local', $digits !== '' ? $digits : Str::lower((string) Str::ulid())),
            'phone' => $phone,
            'role' => 'parent',
            'password' => Str::random(40),
            'email_verified_at' => now(),
        ]);
    }
}
