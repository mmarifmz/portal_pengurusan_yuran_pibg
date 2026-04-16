<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyBilling;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use App\Models\User;
use App\Services\WhatsAppTacSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ParentTacNotificationController extends Controller
{
    public function __construct(private readonly WhatsAppTacSender $whatsAppTacSender)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'family_billing_id' => ['nullable', 'integer', 'exists:family_billings,id'],
            'parent_name' => ['nullable', 'string', 'max:100'],
        ]);

        $phone = preg_replace('/\s+/', '', $validated['phone']) ?: $validated['phone'];
        $selectedBilling = filled($validated['family_billing_id'] ?? null)
            ? FamilyBilling::query()->find($validated['family_billing_id'])
            : null;

        $parent = User::query()
            ->where('role', 'parent')
            ->where('phone', $phone)
            ->first();

        if (! $parent && $selectedBilling) {
            $parent = $this->registerParentForFamily($phone, $selectedBilling, $validated['parent_name'] ?? null);
        }

        if (! $parent) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number not found in parent records.',
            ], 404);
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

        $delivery = $this->whatsAppTacSender->sendTac($phone, $code);

        return response()->json([
            'success' => true,
            'data' => [
                'otp_id' => $otp->id,
                'expires_at' => $otp->expires_at?->toIso8601String(),
                'delivery' => $delivery,
            ],
        ], 201);
    }

    private function registerParentForFamily(string $phone, FamilyBilling $familyBilling, ?string $parentName = null): User
    {
        $familyStudents = Student::query()
            ->where('family_code', $familyBilling->family_code)
            ->orderBy('full_name')
            ->get();

        $resolvedName = (string) ($parentName
            ?: $familyStudents->firstWhere('parent_name')?->parent_name
            ?: $familyStudents->first()?->parent_name
            ?: "Parent {$familyBilling->family_code}");

        $parent = User::query()->create([
            'name' => $resolvedName,
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
            ->update(['parent_phone' => $phone]);

        return $parent;
    }
}
