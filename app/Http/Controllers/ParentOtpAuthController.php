<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\ParentLoginAudit;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use App\Models\User;
use App\Services\ParentAccountService;
use App\Services\ParentAccessLogService;
use App\Services\WhatsAppTacSender;
use App\Support\ParentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ParentOtpAuthController extends Controller
{
    public function __construct(
        private readonly WhatsAppTacSender $whatsAppTacSender,
        private readonly ParentAccountService $parentAccountService,
        private readonly ParentAccessLogService $parentAccessLogService
    ) {
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
            'returnUrl' => $this->sanitizeReturnUrl((string) $request->query('return', ''), $request),
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
            'return_url' => ['nullable', 'string', 'max:1500'],
        ]);

        $phone = ParentPhone::sanitizeInput($validated['phone']);
        $confirmPhoneReset = (bool) ($validated['confirm_phone_reset'] ?? false);

        $selectedBilling = null;
        $parent = User::query()
            ->withRole('parent')
            ->where('phone', $phone)
            ->first();
        $isPaymentTester = (bool) $parent?->isParentTester();

        if (filled($validated['family_billing_id'] ?? null)) {
            $selectedBilling = FamilyBilling::query()->find($validated['family_billing_id']);

            if (! $selectedBilling) {
                return back()->withErrors([
                    'phone' => 'Unable to start payment onboarding for the selected family.',
                ])->withInput();
            }
        }

        if ($selectedBilling && ! $isPaymentTester && ! $this->phoneCanAccessFamilyBilling($phone, $selectedBilling)) {
            if (! $selectedBilling->registerPhone($phone)) {
                return back()->withErrors([
                    'phone' => $this->familyBillingPhoneLimitMessage(),
                ])->withInput();
            }
        }

        if ($selectedBilling && ! $isPaymentTester) {
            $this->registerFamilyPhoneIfPossible($phone, $selectedBilling);
        }

        if (! $selectedBilling && ! $isPaymentTester) {
            $selectedBilling = $this->resolveFamilyBillingForPhone($phone);

            if ($selectedBilling) {
                $this->registerFamilyPhoneIfPossible($phone, $selectedBilling);
            }
        }


        if (! $parent && $selectedBilling) {
            $parent = $this->registerParentForFamily($phone, $selectedBilling);
        }

        if (! $parent) {
            $this->parentAccessLogService->log($request, 'failed_access', [
                'phone' => $phone,
                'family_billing' => $selectedBilling,
                'login_method' => 'otp',
                'meta' => ['reason' => 'no_parent_account'],
            ]);

            return redirect()
                ->route('parent.search', ['contact' => $phone])
                ->with('status', 'Nombor telefon ini belum dipautkan ke akaun parent. Sila cari nama anak dahulu untuk teruskan ke TAC dan bayaran.');
        }

        if ($this->isParentAccessDisabled($parent)) {
            $this->parentAccessLogService->log($request, 'blocked_access', [
                'user' => $parent,
                'phone' => $phone,
                'family_billing' => $selectedBilling,
                'login_method' => 'otp',
                'meta' => ['reason' => 'parent_blocked'],
            ]);

            return back()->withErrors([
                'phone' => 'Akses portal untuk nombor ini telah dinyahaktifkan. Sila hubungi pentadbir sekolah.',
            ])->withInput();
        }

        if ($selectedBilling) {
            session([
                'parent_login_intended_checkout' => $selectedBilling->id,
            ]);
        } else {
            $request->session()->forget('parent_login_intended_checkout');
        }

        // One-time TAC activation: once this number has logged in successfully before,
        // skip TAC and let parent enter straight to dashboard/checkout.
        if ($this->hasCompletedTacActivation($parent, $phone)) {
            return $this->finalizeParentLogin(
                $request,
                $parent,
                $phone,
                'Log masuk berjaya. TAC tidak diperlukan kerana nombor ini sudah disahkan.'
            );
        }

        $cooldownSeconds = max(0, (int) config('services.parent_tac_resend_cooldown_seconds', 90));
        if ($cooldownSeconds > 0) {
            $latestActiveOtp = ParentLoginOtp::query()
                ->where('phone', $phone)
                ->whereNull('used_at')
                ->where('expires_at', '>=', now())
                ->latest('id')
                ->first();

            if ($latestActiveOtp?->created_at) {
                $secondsSinceLastRequest = (int) $latestActiveOtp->created_at->diffInSeconds(now(), true);
                if ($secondsSinceLastRequest < $cooldownSeconds) {
                    $remainingSeconds = max(1, $cooldownSeconds - $secondsSinceLastRequest);

                    return back()->withErrors([
                        'phone' => "TAC baru sahaja dihantar. Sila tunggu {$remainingSeconds} saat sebelum minta TAC semula.",
                    ])->withInput();
                }
            }
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
            'parent_otp_expires_at' => $otp->expires_at?->getTimestamp(),
            'parent_otp_return_url' => $this->sanitizeReturnUrl((string) ($validated['return_url'] ?? ''), $request),
            'parent_otp_preview' => $this->buildOtpPreview($selectedBilling, $phone),
        ]);

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

        $phone = (string) $request->session()->get('parent_otp_phone');
        $expiresAtTimestamp = (int) $request->session()->get('parent_otp_expires_at', 0);

        if ($expiresAtTimestamp <= 0) {
            $latestOtp = ParentLoginOtp::query()
                ->where('phone', $phone)
                ->whereNull('used_at')
                ->latest('id')
                ->first();

            if ($latestOtp?->expires_at) {
                $expiresAtTimestamp = $latestOtp->expires_at->getTimestamp();
                $request->session()->put('parent_otp_expires_at', $expiresAtTimestamp);
            }
        }

        $preview = (array) $request->session()->get('parent_otp_preview', []);

        $otpReturnUrl = (string) $request->session()->get('parent_otp_return_url', route('parent.search'));

        return view('parent.auth.verify-pin', [
            'phone' => $phone,
            'debugCode' => app()->environment('testing') || config('services.whatsapp.debug_show_tac')
                ? $request->session()->get('parent_otp_debug_code')
                : null,
            'otpExpiresAtIso' => $expiresAtTimestamp > 0
                ? now()->setTimestamp($expiresAtTimestamp)->toIso8601String()
                : null,
            'otpReturnUrl' => $otpReturnUrl,
            'otpLoginUrl' => route('parent.login.form', [
                'phone' => $phone,
                'return' => $otpReturnUrl,
            ]),
            'maskedStudentName' => (string) ($preview['masked_student_name'] ?? ''),
            'maskedFamilyCode' => (string) ($preview['masked_family_code'] ?? ''),
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
            $this->parentAccessLogService->log($request, 'failed_access', [
                'phone' => $phone,
                'login_method' => 'otp',
                'meta' => ['reason' => 'otp_expired'],
            ]);

            return back()->withErrors([
                'pin' => 'TAC expired. Please request a new TAC.',
            ]);
        }

        $otp->increment('attempts');

        if (! Hash::check($validated['pin'], $otp->code_hash)) {
            $this->parentAccessLogService->log($request, 'failed_access', [
                'user' => $otp->user_id ? User::query()->find($otp->user_id) : null,
                'phone' => $phone,
                'login_method' => 'otp',
                'meta' => ['reason' => 'invalid_otp'],
            ]);

            if ($otp->attempts >= 5) {
                $otp->update(['used_at' => now()]);
            }

            return back()->withErrors([
                'pin' => 'Invalid TAC pin.',
            ]);
        }

        $otp->update(['used_at' => now()]);

        $user = User::query()
            ->find($otp->user_id);

        if (! $user || ! $user->hasRole('parent')) {
            $this->parentAccessLogService->log($request, 'failed_access', [
                'phone' => $phone,
                'login_method' => 'otp',
                'meta' => ['reason' => 'user_not_parent'],
            ]);

            return back()->withErrors([
                'pin' => 'Unable to authenticate this parent account.',
            ]);
        }

        if ($this->isParentAccessDisabled($user)) {
            $this->parentAccessLogService->log($request, 'blocked_access', [
                'user' => $user,
                'phone' => $phone,
                'login_method' => 'otp',
                'meta' => ['reason' => 'parent_blocked'],
            ]);

            return back()->withErrors([
                'pin' => 'Akses portal untuk nombor ini telah dinyahaktifkan. Sila hubungi pentadbir sekolah.',
            ]);
        }

        return $this->finalizeParentLogin(
            $request,
            $user,
            $phone,
            'Log masuk berjaya. Nombor telefon anda telah disahkan.'
        );
    }

    private function dispatchTac(User $parent, string $code, ?FamilyBilling $selectedBilling = null): void
    {
        $phone = (string) $parent->phone;

        $familyCode = $selectedBilling?->family_code
            ?? Student::query()
                ->whereIn('parent_phone', ParentPhone::variants($phone))
                ->whereNotNull('family_code')
                ->where('family_code', '!=', '')
                ->orderBy('family_code')
                ->value('family_code')
            ?? FamilyBilling::query()
                ->whereHas('phones', fn ($query) => $query->where('normalized_phone', ParentPhone::normalizeForMatch($phone)))
                ->orderBy('family_code')
                ->value('family_code');

        $this->whatsAppTacSender->sendTac($phone, $code, $familyCode);

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

    private function phoneCanAccessFamilyBilling(string $phone, FamilyBilling $familyBilling): bool
    {
        if ($familyBilling->hasRegisteredPhone($phone)) {
            return true;
        }

        return Student::query()
            ->whereIn('parent_phone', ParentPhone::variants($phone))
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

    private function resetPaidFamilyPhone(string $phone, FamilyBilling $familyBilling): ?User
    {
        if (! $familyBilling->registerPhone($phone)) {
            return null;
        }

        $existingParent = User::query()
            ->withRole('parent')
            ->where('phone', ParentPhone::sanitizeInput($phone))
            ->first();

        if ($existingParent) {
            return $existingParent;
        }

        return $this->registerParentForFamily($phone, $familyBilling);
    }

    private function registerParentForFamily(string $phone, FamilyBilling $familyBilling): User
    {
        return $this->parentAccountService->resolveOrCreateForFamily($phone, $familyBilling);
    }

    private function registerFamilyPhoneIfPossible(string $phone, FamilyBilling $familyBilling): void
    {
        if ($familyBilling->hasRegisteredPhone($phone)) {
            return;
        }

        $familyBilling->registerPhone($phone);
    }

    private function familyBillingPhoneLimitMessage(): string
    {
        return 'This family already has the maximum 5 phone numbers registered. Please ask admin to remove one phone first.';
    }

    private function resolveFamilyBillingForPhone(string $phone): ?FamilyBilling
    {
        $normalizedPhone = ParentPhone::normalizeForMatch($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        $familyCodesFromStudents = Student::query()
            ->whereIn('parent_phone', ParentPhone::variants($phone))
            ->whereNotNull('family_code')
            ->pluck('family_code');

        $familyCodesFromRegisteredPhones = FamilyBilling::query()
            ->whereHas('phones', fn ($query) => $query->where('normalized_phone', $normalizedPhone))
            ->pluck('family_code');

        $familyCodes = $familyCodesFromStudents
            ->merge($familyCodesFromRegisteredPhones)
            ->filter()
            ->unique()
            ->values();

        if ($familyCodes->isEmpty()) {
            return null;
        }

        return FamilyBilling::query()
            ->whereIn('family_code', $familyCodes)
            ->orderByDesc('billing_year')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{masked_student_name: string, masked_family_code: string}
     */
    private function buildOtpPreview(?FamilyBilling $selectedBilling, string $phone): array
    {
        $billing = $selectedBilling ?? $this->resolveFamilyBillingForPhone($phone);

        if (! $billing) {
            return [
                'masked_student_name' => '',
                'masked_family_code' => '',
            ];
        }

        $studentName = (string) Student::query()
            ->where('family_code', $billing->family_code)
            ->orderBy('full_name')
            ->value('full_name');

        return [
            'masked_student_name' => $this->maskStudentName($studentName),
            'masked_family_code' => (string) $billing->family_code,
        ];
    }

    private function maskStudentName(string $fullName): string
    {
        $trimmed = trim($fullName);

        if ($trimmed === '') {
            return '';
        }

        $length = mb_strlen($trimmed);
        $visibleLength = max(3, (int) ceil($length * 0.6));
        $maskedLength = max(1, $length - $visibleLength);

        return mb_substr($trimmed, 0, $visibleLength).str_repeat('#', $maskedLength);
    }

    private function maskFamilyCode(string $familyCode): string
    {
        $trimmed = trim($familyCode);

        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) <= 4) {
            return mb_substr($trimmed, 0, 1).str_repeat('#', max(1, mb_strlen($trimmed) - 1));
        }

        return mb_substr($trimmed, 0, 4).str_repeat('#', max(2, mb_strlen($trimmed) - 4));
    }

    private function sanitizeReturnUrl(string $candidate, Request $request): string
    {
        $fallback = route('parent.search');
        $target = trim($candidate);

        if ($target === '') {
            $target = trim((string) $request->headers->get('referer', ''));
        }

        if ($target === '') {
            return $fallback;
        }

        $parts = parse_url($target);

        if (! is_array($parts)) {
            return $fallback;
        }

        if (isset($parts['host']) && strcasecmp((string) $parts['host'], (string) $request->getHost()) !== 0) {
            return $fallback;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');

        if (! str_starts_with($path, '/parent/search')) {
            return $fallback;
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $request->getSchemeAndHttpHost().$path.$query;
    }

    private function isParentAccessDisabled(User $user): bool
    {
        return $user->hasRole('parent')
            && $user->is_active !== null
            && (bool) $user->is_active === false;
    }

    private function hasCompletedTacActivation(User $user, string $phone): bool
    {
        $normalizedPhone = ParentPhone::normalizeForMatch($phone);

        if ($normalizedPhone === '' || ! ParentLoginAudit::tableIsAvailable()) {
            return false;
        }

        $query = ParentLoginAudit::query()
            ->where('normalized_phone', $normalizedPhone);

        if (ParentLoginAudit::hasAuditColumn('action_type')) {
            $query->where(function ($builder): void {
                $builder->whereNull('action_type')
                    ->orWhere('action_type', 'login');
            });
        }

        if ($user->parent_access_reset_at !== null) {
            $query->where(ParentLoginAudit::occurrenceColumn(), '>', $user->parent_access_reset_at);
        }

        return $query->exists();
    }

    private function finalizeParentLogin(
        Request $request,
        User $user,
        string $phone,
        string $defaultStatusMessage
    ): RedirectResponse {
        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->put('active_portal_space', 'parent');
        $request->session()->put('parent_login_method', 'otp');

        $intendedCheckoutId = (int) $request->session()->get('parent_login_intended_checkout', 0);
        $returnUrl = (string) $request->session()->get('parent_otp_return_url', route('parent.search'));

        $this->parentAccessLogService->log($request, 'login', [
            'user' => $user,
            'phone' => $phone,
            'login_method' => 'otp',
        ]);

        $request->session()->forget('parent_login_intended_checkout');
        $request->session()->forget([
            'parent_otp_phone',
            'parent_otp_debug_code',
            'parent_otp_expires_at',
            'parent_otp_return_url',
            'parent_otp_preview',
        ]);

        $request->session()->put('parent_child_selection_completed', false);
        $request->session()->forget('parent_selected_family_billing_id');

        if ($intendedCheckoutId > 0) {
            $billing = FamilyBilling::query()->find($intendedCheckoutId);

            if ($billing && $this->userCanAccessFamilyBilling($user, $billing)) {
                $request->session()->put('parent_child_selection_completed', true);
                $request->session()->put('parent_selected_family_billing_id', $billing->id);

                return redirect()
                    ->route('parent.payments.checkout', $billing)
                    ->with('status', $defaultStatusMessage);
            }
        }

        if ($user->isStaff()) {
            return redirect()
                ->route('dashboard')
                ->with('status', $defaultStatusMessage);
        }

        return redirect()
            ->to($returnUrl !== '' ? $returnUrl : route('parent.search'))
            ->with('status', $defaultStatusMessage);
    }
}
