<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\ParentLoginAudit;
use App\Support\ParentPhone;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TeacherFamilyLoginMonitorController extends Controller
{
    public function index(): View
    {
        $latestBillingIds = FamilyBilling::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('family_code')
            ->pluck('id');

        $families = FamilyBilling::query()
            ->whereIn('id', $latestBillingIds)
            ->whereHas('phones')
            ->with(['phones' => fn ($query) => $query->orderBy('id')])
            ->orderBy('family_code')
            ->get();

        $loginByPhone = ParentLoginAudit::query()
            ->selectRaw('normalized_phone, COUNT(*) as login_count, MAX(logged_in_at) as latest_login_at')
            ->groupBy('normalized_phone')
            ->get()
            ->keyBy('normalized_phone');

        $rows = $families->map(function (FamilyBilling $familyBilling) use ($loginByPhone): array {
            $phones = $familyBilling->phones
                ->pluck('phone')
                ->map(fn ($phone) => ParentPhone::sanitizeInput((string) $phone))
                ->filter()
                ->unique()
                ->values();

            $normalizedPhones = $phones
                ->map(fn (string $phone) => ParentPhone::normalizeForMatch($phone))
                ->filter()
                ->unique()
                ->values();

            $loginCount = 0;
            $latestLoginAt = null;

            foreach ($normalizedPhones as $normalizedPhone) {
                $aggregate = $loginByPhone->get($normalizedPhone);

                if (! $aggregate) {
                    continue;
                }

                $loginCount += (int) $aggregate->login_count;

                $candidateTimestamp = $aggregate->latest_login_at;
                if ($candidateTimestamp) {
                    $candidate = Carbon::parse((string) $candidateTimestamp);
                    if (! $latestLoginAt || $candidate->gt($latestLoginAt)) {
                        $latestLoginAt = $candidate;
                    }
                }
            }

            return [
                'family_code' => (string) $familyBilling->family_code,
                'phones_display' => $phones->implode(', '),
                'login_count' => $loginCount,
                'latest_login_at' => $latestLoginAt,
                'is_paid' => $familyBilling->outstanding_amount <= 0,
            ];
        })->values();

        return view('teacher.family-login-monitor', [
            'rows' => $rows,
            'generatedAt' => now(),
            'totalFamilies' => $rows->count(),
            'totalLoginCount' => $rows->sum('login_count'),
        ]);
    }
}