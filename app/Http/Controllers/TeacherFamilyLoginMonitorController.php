<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\ParentLoginAudit;
use App\Models\ParentLoginInvite;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use App\Models\User;
use App\Services\WhatsAppTacSender;
use App\Support\ParentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TeacherFamilyLoginMonitorController extends Controller
{
    public function __construct(private readonly WhatsAppTacSender $whatsAppTacSender)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q')->toString());
        $paidStatus = (string) $request->string('paid_status', 'all')->toString();
        if (! in_array($paidStatus, ['all', 'paid', 'unpaid'], true)) {
            $paidStatus = 'all';
        }

        $tacStatus = (string) $request->string('tac_status', 'all')->toString();
        if (! in_array($tacStatus, ['all', 'stuck', 'completed', 'pending', 'expired', 'no_request'], true)) {
            $tacStatus = 'all';
        }

        $selectedClass = trim((string) $request->string('class_name')->toString());
        $dateFromInput = trim((string) $request->string('date_from')->toString());
        $dateToInput = trim((string) $request->string('date_to')->toString());

        $dateFrom = null;
        if ($dateFromInput !== '') {
            try {
                $dateFrom = Carbon::parse($dateFromInput)->startOfDay();
            } catch (\Throwable) {
                $dateFrom = null;
                $dateFromInput = '';
            }
        }

        $dateTo = null;
        if ($dateToInput !== '') {
            try {
                $dateTo = Carbon::parse($dateToInput)->endOfDay();
            } catch (\Throwable) {
                $dateTo = null;
                $dateToInput = '';
            }
        }

        $classOptions = Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->values();

        $familyClasses = Student::query()
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->get(['family_code', 'class_name'])
            ->groupBy('family_code')
            ->map(fn ($rows) => $rows->pluck('class_name')->filter()->unique()->sort()->values());

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

        $otpByNormalizedPhone = ParentLoginOtp::query()
            ->get(['phone', 'used_at', 'expires_at', 'created_at'])
            ->reduce(function (Collection $carry, ParentLoginOtp $otp): Collection {
                $normalizedPhone = ParentPhone::normalizeForMatch((string) $otp->phone);

                if ($normalizedPhone === '') {
                    return $carry;
                }

                $aggregate = $carry->get($normalizedPhone, [
                    'tac_sent_count' => 0,
                    'tac_verified_count' => 0,
                    'tac_pending_count' => 0,
                    'tac_expired_count' => 0,
                    'latest_tac_sent_at' => null,
                ]);

                $aggregate['tac_sent_count']++;

                if ($otp->used_at !== null) {
                    $aggregate['tac_verified_count']++;
                } elseif ($otp->expires_at !== null && $otp->expires_at->isPast()) {
                    $aggregate['tac_expired_count']++;
                } else {
                    $aggregate['tac_pending_count']++;
                }

                $createdAt = $otp->created_at;
                if ($createdAt !== null && (! $aggregate['latest_tac_sent_at'] || $createdAt->gt($aggregate['latest_tac_sent_at']))) {
                    $aggregate['latest_tac_sent_at'] = $createdAt;
                }

                $carry->put($normalizedPhone, $aggregate);

                return $carry;
            }, collect());

        $inviteByFamilyPhone = ParentLoginInvite::query()
            ->whereNotNull('sent_at')
            ->get(['family_billing_id', 'normalized_phone', 'sent_at'])
            ->groupBy('family_billing_id')
            ->map(function (Collection $rowsByFamily): Collection {
                return $rowsByFamily
                    ->groupBy('normalized_phone')
                    ->map(function (Collection $rowsByPhone): array {
                        $latestSentAt = $rowsByPhone
                            ->pluck('sent_at')
                            ->filter()
                            ->sort()
                            ->last();

                        return [
                            'invite_sent_count' => $rowsByPhone->count(),
                            'latest_invite_sent_at' => $latestSentAt,
                        ];
                    });
            });

        $rows = $families->map(function (FamilyBilling $familyBilling) use ($loginByPhone, $otpByNormalizedPhone, $inviteByFamilyPhone, $familyClasses): array {
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
            $tacSentCount = 0;
            $tacVerifiedCount = 0;
            $tacPendingCount = 0;
            $tacExpiredCount = 0;
            $latestTacSentAt = null;
            $inviteSentCount = 0;
            $latestInviteSentAt = null;
            $suggestedInvitePhone = null;
            $suggestedInviteScore = null;

            foreach ($normalizedPhones as $normalizedPhone) {
                $loginAggregate = $loginByPhone->get($normalizedPhone);

                if ($loginAggregate) {
                    $loginCount += (int) $loginAggregate->login_count;

                    $loginTimestamp = $loginAggregate->latest_login_at;
                    if ($loginTimestamp) {
                        $candidate = Carbon::parse((string) $loginTimestamp);
                        if (! $latestLoginAt || $candidate->gt($latestLoginAt)) {
                            $latestLoginAt = $candidate;
                        }
                    }
                }

                $otpAggregate = $otpByNormalizedPhone->get($normalizedPhone);
                if (! $otpAggregate) {
                    continue;
                }

                $tacSentCount += (int) ($otpAggregate['tac_sent_count'] ?? 0);
                $tacVerifiedCount += (int) ($otpAggregate['tac_verified_count'] ?? 0);
                $tacPendingCount += (int) ($otpAggregate['tac_pending_count'] ?? 0);
                $tacExpiredCount += (int) ($otpAggregate['tac_expired_count'] ?? 0);

                $candidateTacTimestamp = $otpAggregate['latest_tac_sent_at'] ?? null;
                if ($candidateTacTimestamp && (! $latestTacSentAt || $candidateTacTimestamp->gt($latestTacSentAt))) {
                    $latestTacSentAt = $candidateTacTimestamp;
                }

                $inviteAggregate = $inviteByFamilyPhone
                    ->get($familyBilling->id, collect())
                    ->get($normalizedPhone);

                if ($inviteAggregate) {
                    $inviteSentCount += (int) ($inviteAggregate['invite_sent_count'] ?? 0);

                    $candidateInviteTimestamp = $inviteAggregate['latest_invite_sent_at'] ?? null;
                    if ($candidateInviteTimestamp && (! $latestInviteSentAt || Carbon::parse((string) $candidateInviteTimestamp)->gt($latestInviteSentAt))) {
                        $latestInviteSentAt = Carbon::parse((string) $candidateInviteTimestamp);
                    }
                }

                $shouldSuggestThisPhone = (($otpAggregate['tac_verified_count'] ?? 0) === 0)
                    && (($otpAggregate['tac_sent_count'] ?? 0) > 0);

                if ($shouldSuggestThisPhone) {
                    $phoneScore = Carbon::parse((string) ($otpAggregate['latest_tac_sent_at'] ?? now()))->timestamp;
                    $candidateDisplayPhone = $phones
                        ->first(fn (string $phone) => ParentPhone::normalizeForMatch($phone) === $normalizedPhone);

                    if ($candidateDisplayPhone && ($suggestedInviteScore === null || $phoneScore > $suggestedInviteScore)) {
                        $suggestedInviteScore = $phoneScore;
                        $suggestedInvitePhone = $candidateDisplayPhone;
                    }
                }
            }

            $tacStatus = 'No TAC request';
            if ($tacSentCount > 0) {
                if ($loginCount > 0 || $tacVerifiedCount > 0) {
                    $tacStatus = 'Completed';
                } elseif ($tacPendingCount > 0) {
                    $tacStatus = 'Pending TAC';
                } elseif ($tacExpiredCount > 0) {
                    $tacStatus = 'Expired TAC';
                } else {
                    $tacStatus = 'TAC requested';
                }
            }

            $isTacStuck = $tacSentCount > 0 && $loginCount === 0 && $tacVerifiedCount === 0;

            return [
                'family_billing_id' => (int) $familyBilling->id,
                'family_code' => (string) $familyBilling->family_code,
                'phones_display' => $phones->implode(', '),
                'classes' => $familyClasses->get((string) $familyBilling->family_code, collect()),
                'class_display' => $familyClasses->get((string) $familyBilling->family_code, collect())->implode(', '),
                'login_count' => $loginCount,
                'latest_login_at' => $latestLoginAt,
                'tac_sent_count' => $tacSentCount,
                'tac_verified_count' => $tacVerifiedCount,
                'tac_pending_count' => $tacPendingCount,
                'tac_expired_count' => $tacExpiredCount,
                'latest_tac_sent_at' => $latestTacSentAt,
                'tac_status' => $tacStatus,
                'is_tac_stuck' => $isTacStuck,
                'invite_sent_count' => $inviteSentCount,
                'latest_invite_sent_at' => $latestInviteSentAt,
                'invite_phone' => $suggestedInvitePhone ?: $phones->first(),
                'is_paid' => $familyBilling->outstanding_amount <= 0,
            ];
        });

        $rows = $rows
            ->when($search !== '', function ($collection) use ($search) {
                $needle = mb_strtolower($search);

                return $collection->filter(function (array $row) use ($needle): bool {
                    return str_contains(mb_strtolower((string) ($row['family_code'] ?? '')), $needle)
                        || str_contains(mb_strtolower((string) ($row['phones_display'] ?? '')), $needle)
                        || str_contains(mb_strtolower((string) ($row['class_display'] ?? '')), $needle);
                });
            })
            ->when($paidStatus === 'paid', fn ($collection) => $collection->where('is_paid', true))
            ->when($paidStatus === 'unpaid', fn ($collection) => $collection->where('is_paid', false))
            ->when($tacStatus === 'stuck', fn ($collection) => $collection->where('is_tac_stuck', true))
            ->when($tacStatus === 'completed', fn ($collection) => $collection->where('tac_status', 'Completed'))
            ->when($tacStatus === 'pending', fn ($collection) => $collection->where('tac_status', 'Pending TAC'))
            ->when($tacStatus === 'expired', fn ($collection) => $collection->where('tac_status', 'Expired TAC'))
            ->when($tacStatus === 'no_request', fn ($collection) => $collection->where('tac_status', 'No TAC request'))
            ->when($selectedClass !== '', fn ($collection) => $collection->filter(
                fn (array $row) => collect($row['classes'] ?? [])->contains($selectedClass)
            ))
            ->when($dateFrom !== null, fn ($collection) => $collection->filter(
                fn (array $row) => $row['latest_login_at'] !== null && $row['latest_login_at']->greaterThanOrEqualTo($dateFrom)
            ))
            ->when($dateTo !== null, fn ($collection) => $collection->filter(
                fn (array $row) => $row['latest_login_at'] !== null && $row['latest_login_at']->lessThanOrEqualTo($dateTo)
            ))
            ->sort(function (array $a, array $b): int {
                $aTs = $a['latest_login_at']?->timestamp ?? 0;
                $bTs = $b['latest_login_at']?->timestamp ?? 0;

                if ($aTs !== $bTs) {
                    return $bTs <=> $aTs;
                }

                $aCount = (int) ($a['login_count'] ?? 0);
                $bCount = (int) ($b['login_count'] ?? 0);

                if ($aCount !== $bCount) {
                    return $bCount <=> $aCount;
                }

                return strcmp((string) ($a['family_code'] ?? ''), (string) ($b['family_code'] ?? ''));
            })
            ->values();

        return view('teacher.family-login-monitor', [
            'rows' => $rows,
            'generatedAt' => now(),
            'totalFamilies' => $rows->count(),
            'totalLoginCount' => $rows->sum('login_count'),
            'totalTacSentCount' => $rows->sum('tac_sent_count'),
            'totalTacStuckFamilies' => $rows->where('is_tac_stuck', true)->count(),
            'search' => $search,
            'paidStatus' => $paidStatus,
            'tacStatus' => $tacStatus,
            'selectedClass' => $selectedClass,
            'classOptions' => $classOptions,
            'dateFromInput' => $dateFromInput,
            'dateToInput' => $dateToInput,
        ]);
    }

    public function sendInvite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'family_billing_id' => ['required', 'integer'],
            'phone' => ['required', 'string', 'max:25'],
        ]);

        $familyBilling = FamilyBilling::query()->find($validated['family_billing_id']);
        if (! $familyBilling) {
            return redirect()->route('teacher.family-login-monitor')
                ->withErrors(['q' => 'Family billing record not found.']);
        }

        $phone = ParentPhone::sanitizeInput($validated['phone']);
        $normalizedPhone = ParentPhone::normalizeForMatch($phone);

        if ($normalizedPhone === '') {
            return redirect()->route('teacher.family-login-monitor')
                ->withErrors(['q' => 'Invalid phone number for invite action.']);
        }

        if (! $familyBilling->hasRegisteredPhone($phone) && ! $familyBilling->registerPhone($phone)) {
            return redirect()->route('teacher.family-login-monitor')
                ->withErrors(['q' => 'This family already has the maximum 5 phone numbers registered.']);
        }

        $parent = User::query()
            ->where('role', 'parent')
            ->where('phone', $phone)
            ->first();

        if (! $parent) {
            $parent = $this->registerParentForFamily($phone, $familyBilling);
        }

        if ($parent->is_active !== null && (bool) $parent->is_active === false) {
            return redirect()->route('teacher.family-login-monitor')
                ->withErrors(['q' => 'Parent portal access for this number is disabled.']);
        }

        ParentLoginInvite::query()
            ->where('family_billing_id', $familyBilling->id)
            ->where('normalized_phone', $normalizedPhone)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $invite = ParentLoginInvite::query()->create([
            'family_billing_id' => $familyBilling->id,
            'user_id' => $parent->id,
            'phone' => $phone,
            'normalized_phone' => $normalizedPhone,
            'token' => Str::random(80),
            'expires_at' => now()->addHours(24),
            'created_by_user_id' => $request->user()?->id,
        ]);

        $loginLink = route('parent.invite.login', ['token' => $invite->token]);

        $message = "Assalamualaikum & Salam Sejahtera, nombor telefon anda telah didaftarkan ke Portal PIBG SSP secara manual.\n"
            ."Sila klik link ini untuk membuat bayaran yuran : <{$loginLink}>\n"
            ."Pautan ini sah selama 24 jam.";

        try {
            $this->whatsAppTacSender->sendMessage($phone, $message);
        } catch (\Throwable $exception) {
            $invite->delete();

            return redirect()->route('teacher.family-login-monitor')
                ->withErrors(['q' => 'Failed to send invite message: '.$exception->getMessage()]);
        }

        $invite->forceFill([
            'sent_at' => now(),
        ])->save();

        return redirect()->route('teacher.family-login-monitor')
            ->with('status', "Invite sent to {$phone}. Link valid for 24 hours.");
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
