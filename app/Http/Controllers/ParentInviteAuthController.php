<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\ParentLoginAudit;
use App\Models\ParentLoginInvite;
use App\Models\Student;
use App\Models\User;
use App\Services\ParentAccountService;
use App\Services\ParentAccessLogService;
use App\Support\ParentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParentInviteAuthController extends Controller
{
    public function __construct(
        private readonly ParentAccountService $parentAccountService,
        private readonly ParentAccessLogService $parentAccessLogService
    ) {
    }

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $invite = ParentLoginInvite::query()
            ->where('token', $token)
            ->whereNotNull('sent_at')
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->first();

        if (! $invite) {
            $this->parentAccessLogService->log($request, 'failed_access', [
                'phone' => '',
                'login_method' => 'invite',
                'meta' => ['reason' => 'invalid_invite_token'],
            ]);

            return redirect()->route('parent.login.form')
                ->withErrors([
                    'phone' => 'Pautan jemputan tidak sah atau telah tamat tempoh. Sila minta jemputan baharu.',
                ]);
        }

        $familyBilling = FamilyBilling::query()->find($invite->family_billing_id);

        if (! $familyBilling) {
            return redirect()->route('parent.login.form')
                ->withErrors([
                    'phone' => 'Rekod keluarga tidak ditemui. Sila hubungi pihak sekolah.',
                ]);
        }

        $phone = ParentPhone::sanitizeInput((string) $invite->phone);
        $normalizedPhone = ParentPhone::normalizeForMatch($phone);

        if ($normalizedPhone === '') {
            return redirect()->route('parent.login.form')
                ->withErrors([
                    'phone' => 'Nombor telefon jemputan tidak sah.',
                ]);
        }

        if (! $familyBilling->hasRegisteredPhone($phone) && ! $familyBilling->registerPhone($phone)) {
            return redirect()->route('parent.login.form')
                ->withErrors([
                    'phone' => 'Keluarga ini sudah capai had 5 nombor telefon. Sila hubungi pentadbir sekolah.',
                ]);
        }

        $parent = User::query()
            ->withRole('parent')
            ->where('phone', $phone)
            ->first();

        if (! $parent) {
            $parent = $this->registerParentForFamily($phone, $familyBilling);
        }

        if ($parent->is_active !== null && (bool) $parent->is_active === false) {
            $this->parentAccessLogService->log($request, 'blocked_access', [
                'user' => $parent,
                'phone' => $phone,
                'family_billing' => $familyBilling,
                'login_method' => 'invite',
                'meta' => ['reason' => 'parent_blocked'],
            ]);

            return redirect()->route('parent.login.form')
                ->withErrors([
                    'phone' => 'Akses portal untuk nombor ini telah dinyahaktifkan. Sila hubungi pentadbir sekolah.',
                ]);
        }

        $invite->forceFill([
            'used_at' => now(),
            'user_id' => $parent->id,
        ])->save();

        Auth::login($parent, false);
        $request->session()->regenerate();
        $request->session()->put('active_portal_space', 'parent');
        $request->session()->put('parent_login_method', 'invite');

        $this->parentAccessLogService->log($request, 'login', [
            'user' => $parent,
            'phone' => $phone,
            'family_billing' => $familyBilling,
            'login_method' => 'invite',
        ]);

        $request->session()->put('parent_child_selection_completed', true);
        $request->session()->put('parent_selected_family_billing_id', $familyBilling->id);

        return redirect()->route('parent.payments.checkout', $familyBilling)
            ->with('status', 'Akses berjaya. Nombor telefon anda telah didaftarkan ke portal secara manual.');
    }

    private function registerParentForFamily(string $phone, FamilyBilling $familyBilling): User
    {
        return $this->parentAccountService->resolveOrCreateForFamily($phone, $familyBilling);
    }
}
