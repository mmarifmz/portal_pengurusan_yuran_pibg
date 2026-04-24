<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\ParentLoginAudit;
use App\Models\ParentLoginInvite;
use App\Models\Student;
use App\Models\User;
use App\Support\ParentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ParentInviteAuthController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $invite = ParentLoginInvite::query()
            ->where('token', $token)
            ->whereNotNull('sent_at')
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->first();

        if (! $invite) {
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
            ->where('role', 'parent')
            ->where('phone', $phone)
            ->first();

        if (! $parent) {
            $parent = $this->registerParentForFamily($phone, $familyBilling);
        }

        if ($parent->is_active !== null && (bool) $parent->is_active === false) {
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

        ParentLoginAudit::query()->create([
            'user_id' => $parent->id,
            'phone' => $phone,
            'normalized_phone' => $normalizedPhone,
            'logged_in_at' => now(),
        ]);

        $request->session()->put('parent_child_selection_completed', true);
        $request->session()->put('parent_selected_family_billing_id', $familyBilling->id);

        return redirect()->route('parent.payments.checkout', $familyBilling)
            ->with('status', 'Akses berjaya. Nombor telefon anda telah didaftarkan ke portal secara manual.');
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

        $sanitizedPhone = ParentPhone::sanitizeInput($phone);

        $parent = User::query()->create([
            'name' => $parentName,
            'email' => sprintf(
                'parent-%s-%s@placeholder.local',
                Str::lower($familyBilling->family_code),
                preg_replace('/\D+/', '', $sanitizedPhone) ?: Str::lower((string) Str::ulid())
            ),
            'phone' => $sanitizedPhone,
            'role' => 'parent',
            'password' => Str::random(40),
            'email_verified_at' => now(),
        ]);

        Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->where(function ($query) {
                $query->whereNull('parent_phone')->orWhere('parent_phone', '');
            })
            ->update([
                'parent_phone' => $sanitizedPhone,
            ]);

        return $parent;
    }
}
