<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\FamilyBillingPhone;
use App\Models\ParentLoginAudit;
use App\Models\ParentLoginInvite;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use App\Models\User;
use App\Services\ParentPaymentNotificationService;
use App\Services\WhatsAppTacSender;
use App\Support\ParentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentTesterUserController extends Controller
{
    public function index(Request $request): View
    {
        $keyword = trim((string) $request->string('q')->toString());
        $hasPaymentTesterColumn = Schema::hasColumn('users', 'is_payment_tester');

        $parentUsersQuery = User::query()
            ->where('role', 'parent')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($nested) use ($keyword): void {
                    $nested->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%");
                });
            });

        if ($hasPaymentTesterColumn) {
            $parentUsersQuery->orderByDesc('is_payment_tester');
        } else {
            $parentUsersQuery->select('users.*')->selectRaw('false as is_payment_tester');
        }

        $parentUsers = $parentUsersQuery
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $dummyFamily = FamilyBilling::query()
            ->where('family_code', 'TEST-DUMMY-PORTAL')
            ->where('billing_year', 2099)
            ->first();

        $portalTestInvites = collect();
        if ($dummyFamily) {
            $portalTestInvites = ParentLoginInvite::query()
                ->where('family_billing_id', $dummyFamily->id)
                ->whereNotNull('sent_at')
                ->latest('sent_at')
                ->limit(30)
                ->get();
        }

        $successfulPaymentSamples = FamilyPaymentTransaction::query()
            ->with('familyBilling:id,family_code,billing_year')
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->whereNotNull('payer_phone')
            ->where('payer_phone', '!=', '')
            ->latest('paid_at')
            ->limit(80)
            ->get();

        return view('system.payment-testers', [
            'parentUsers' => $parentUsers,
            'keyword' => $keyword,
            'hasPaymentTesterColumn' => $hasPaymentTesterColumn,
            'defaultWhatsappTestPhone' => (string) config('services.treasury_whatsapp_phone', ''),
            'defaultWhatsappTestMessage' => 'Ini mesej ujian WhatsApp dari Portal PIBG.',
            'portalTestInvites' => $portalTestInvites,
            'successfulPaymentSamples' => $successfulPaymentSamples,
            'portalTestFamilyCode' => 'TEST-DUMMY-PORTAL',
        ]);
    }

    public function createPortalTestInvite(Request $request, WhatsAppTacSender $whatsAppTacSender): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'send_whatsapp' => ['nullable', 'boolean'],
        ]);

        $phone = ParentPhone::sanitizeInput((string) $validated['phone']);
        $normalizedPhone = ParentPhone::normalizeForMatch($phone);

        if ($normalizedPhone === '') {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'Phone format is invalid for test invite.');
        }

        $dummyFamily = $this->resolvePortalTestDummyFamily();

        if (! $dummyFamily->hasRegisteredPhone($phone) && ! $dummyFamily->registerPhone($phone)) {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'Dummy family has reached max 5 phone entries. Please clear old test phones first.');
        }

        $parent = User::query()
            ->where('role', 'parent')
            ->where('phone', $phone)
            ->first();

        if (! $parent) {
            $parent = User::query()->create([
                'name' => 'PARENT TEST '.$phone,
                'email' => sprintf(
                    'parent-test-%s-%s@placeholder.local',
                    Str::lower($dummyFamily->family_code),
                    preg_replace('/\D+/', '', $phone) ?: Str::lower((string) Str::ulid())
                ),
                'phone' => $phone,
                'role' => 'parent',
                'password' => Str::random(40),
                'email_verified_at' => now(),
                'is_payment_tester' => true,
            ]);
        } elseif (! $parent->is_payment_tester) {
            $parent->forceFill(['is_payment_tester' => true])->save();
        }

        ParentLoginInvite::query()
            ->where('family_billing_id', $dummyFamily->id)
            ->where('normalized_phone', $normalizedPhone)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $invite = ParentLoginInvite::query()->create([
            'family_billing_id' => $dummyFamily->id,
            'user_id' => $parent->id,
            'phone' => $phone,
            'normalized_phone' => $normalizedPhone,
            'token' => Str::random(80),
            'expires_at' => now()->addHours(24),
            'created_by_user_id' => $request->user()?->id,
            'sent_at' => now(),
        ]);

        $loginLink = route('parent.invite.login', ['token' => $invite->token]);
        $message = "Assalamualaikum & Salam Sejahtera, nombor telefon anda telah didaftarkan ke Portal PIBG SSP secara manual.\n"
            ."Sila klik link ini untuk membuat bayaran yuran : <{$loginLink}>\n"
            ."Pautan ini sah selama 24 jam.";

        $sendWhatsapp = (bool) ($validated['send_whatsapp'] ?? true);

        if ($sendWhatsapp) {
            try {
                $whatsAppTacSender->sendMessage($phone, $message);
            } catch (\Throwable $exception) {
                $invite->delete();

                return redirect()
                    ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                    ->with('error', 'Test invite created but WhatsApp send failed: '.$exception->getMessage());
            }
        }

        return redirect()
            ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
            ->with('status', "Portal test invite ready for {$phone}. Valid for 24 hours. Link: {$loginLink}");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'parent', 404);

        if (! Schema::hasColumn('users', 'is_payment_tester')) {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'Payment tester column not found. Please run migration: php artisan migrate --path=database/migrations/2026_04_17_000006_add_is_payment_tester_to_users_table.php');
        }

        $validated = $request->validate([
            'is_payment_tester' => ['required', 'boolean'],
        ]);

        $user->forceFill([
            'is_payment_tester' => (bool) $validated['is_payment_tester'],
        ])->save();

        return redirect()
            ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
            ->with('status', $user->is_payment_tester
                ? 'Payment tester enabled for this parent user.'
                : 'Payment tester disabled for this parent user.');
    }

    public function sendWhatsappTest(Request $request, WhatsAppTacSender $whatsAppTacSender): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'mode' => ['required', 'string', 'in:message,tac'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $phone = trim((string) $validated['phone']);
        $mode = (string) $validated['mode'];
        $message = trim((string) ($validated['message'] ?? ''));

        try {
            $result = $mode === 'tac'
                ? $whatsAppTacSender->sendTac($phone, (string) random_int(100000, 999999), 'TEST-FAMILY')
                : $whatsAppTacSender->sendMessage(
                    $phone,
                    $message !== '' ? $message : 'Ini mesej ujian WhatsApp dari Portal PIBG.'
                );
        } catch (\Throwable $exception) {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'WhatsApp test failed: '.$exception->getMessage());
        }

        $statusText = (string) ($result['status'] ?? 'sent');
        $messageId = (string) ($result['message_id'] ?? '');

        return redirect()
            ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
            ->with('status', 'WhatsApp test sent successfully. Status: '.$statusText.($messageId !== '' ? ' | Message ID: '.$messageId : ''));
    }

    public function sendPaymentSuccessWhatsappTest(
        Request $request,
        ParentPaymentNotificationService $paymentNotificationService,
        WhatsAppTacSender $whatsAppTacSender
    ): RedirectResponse {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
            'phone' => ['nullable', 'string', 'max:25'],
        ]);

        $transaction = FamilyPaymentTransaction::query()
            ->with('familyBilling:id,family_code')
            ->whereKey((int) $validated['transaction_id'])
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->first();

        if (! $transaction) {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'Selected payment record is not a successful paid transaction.');
        }

        $targetPhone = trim((string) ($validated['phone'] ?? ''));
        if ($targetPhone === '') {
            $targetPhone = trim((string) ($transaction->payer_phone ?? ''));
        }

        if (ParentPhone::normalizeForMatch($targetPhone) === '') {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'Unable to send test payment message: target phone is empty or invalid.');
        }

        try {
            $message = $paymentNotificationService->buildPaymentReceiptMessagePreview($transaction);
            $result = $whatsAppTacSender->sendMessage($targetPhone, $message);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'Payment success WhatsApp test failed: '.$exception->getMessage());
        }

        $familyCode = (string) ($transaction->familyBilling?->family_code ?? '-');
        $statusText = (string) ($result['status'] ?? 'sent');

        return redirect()
            ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
            ->with('status', sprintf(
                'Payment success WhatsApp test sent. Family: %s | Phone: %s | Status: %s',
                $familyCode,
                $targetPhone,
                $statusText
            ));
    }

    public function resetParentPhone(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'clear_student_phone' => ['nullable', 'boolean'],
        ]);

        $phone = trim((string) $validated['phone']);
        $variants = ParentPhone::variants($phone);
        $normalized = ParentPhone::normalizeForMatch($phone);
        $clearStudentPhone = (bool) ($validated['clear_student_phone'] ?? false);

        if ($variants === [] || $normalized === '') {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'Phone format is invalid.');
        }

        $summary = DB::transaction(function () use ($variants, $normalized, $clearStudentPhone): array {
            $otpDeleted = ParentLoginOtp::query()
                ->whereIn('phone', $variants)
                ->delete();

            $auditDeleted = ParentLoginAudit::query()
                ->whereIn('phone', $variants)
                ->orWhere('normalized_phone', $normalized)
                ->delete();

            $parentUsersDeleted = User::query()
                ->where('role', 'parent')
                ->whereIn('phone', $variants)
                ->delete();

            $familyPhonesDeleted = FamilyBillingPhone::query()
                ->where('normalized_phone', $normalized)
                ->orWhereIn('phone', $variants)
                ->delete();

            $studentsUpdated = 0;
            if ($clearStudentPhone) {
                $studentsUpdated = Student::query()
                    ->whereIn('parent_phone', $variants)
                    ->update(['parent_phone' => null]);
            }

            return [
                'otp_deleted' => $otpDeleted,
                'audit_deleted' => $auditDeleted,
                'parent_users_deleted' => $parentUsersDeleted,
                'family_phones_deleted' => $familyPhonesDeleted,
                'students_updated' => $studentsUpdated,
            ];
        });

        return redirect()
            ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
            ->with('status', sprintf(
                'Phone reset done. Parent users: %d, family phones: %d, TAC logs: %d, login logs: %d, students cleared: %d.',
                $summary['parent_users_deleted'],
                $summary['family_phones_deleted'],
                $summary['otp_deleted'],
                $summary['audit_deleted'],
                $summary['students_updated'],
            ));
    }

    public function correctParentPhone(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_phone' => ['required', 'string', 'max:25'],
            'to_phone' => ['required', 'string', 'max:25'],
        ]);

        $fromPhone = trim((string) $validated['from_phone']);
        $toPhone = ParentPhone::sanitizeInput((string) $validated['to_phone']);
        $fromVariants = ParentPhone::variants($fromPhone);
        $fromNormalized = ParentPhone::normalizeForMatch($fromPhone);
        $toNormalized = ParentPhone::normalizeForMatch($toPhone);

        if ($fromVariants === [] || $fromNormalized === '' || $toNormalized === '') {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'From/To phone format is invalid.');
        }

        if ($fromNormalized === $toNormalized) {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'Wrong phone and corrected phone are the same after normalization.');
        }

        $existingTargetParentCount = User::query()
            ->where('role', 'parent')
            ->whereIn('phone', ParentPhone::variants($toPhone))
            ->count();

        if ($existingTargetParentCount > 0) {
            return redirect()
                ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
                ->with('error', 'Target phone already exists on a parent account. Merge manually to avoid conflict.');
        }

        $summary = DB::transaction(function () use ($fromVariants, $fromNormalized, $toPhone, $toNormalized): array {
            $parentUsersUpdated = User::query()
                ->where('role', 'parent')
                ->whereIn('phone', $fromVariants)
                ->update(['phone' => $toPhone]);

            $studentsUpdated = Student::query()
                ->whereIn('parent_phone', $fromVariants)
                ->update(['parent_phone' => $toPhone]);

            $otpUpdated = ParentLoginOtp::query()
                ->whereIn('phone', $fromVariants)
                ->update(['phone' => $toPhone]);

            $auditUpdated = ParentLoginAudit::query()
                ->where(function ($query) use ($fromVariants, $fromNormalized): void {
                    $query->whereIn('phone', $fromVariants)
                        ->orWhere('normalized_phone', $fromNormalized);
                })
                ->update([
                    'phone' => $toPhone,
                    'normalized_phone' => $toNormalized,
                ]);

            $familyPhoneUpdated = 0;
            $familyPhoneDeleted = 0;
            $rows = FamilyBillingPhone::query()
                ->where(function ($query) use ($fromVariants, $fromNormalized): void {
                    $query->whereIn('phone', $fromVariants)
                        ->orWhere('normalized_phone', $fromNormalized);
                })
                ->get();

            foreach ($rows as $row) {
                $existsTarget = FamilyBillingPhone::query()
                    ->where('family_billing_id', $row->family_billing_id)
                    ->where('normalized_phone', $toNormalized)
                    ->where('id', '!=', $row->id)
                    ->exists();

                if ($existsTarget) {
                    $row->delete();
                    $familyPhoneDeleted++;
                    continue;
                }

                $row->forceFill([
                    'phone' => $toPhone,
                    'normalized_phone' => $toNormalized,
                ])->save();
                $familyPhoneUpdated++;
            }

            return [
                'parent_users_updated' => $parentUsersUpdated,
                'students_updated' => $studentsUpdated,
                'otp_updated' => $otpUpdated,
                'audit_updated' => $auditUpdated,
                'family_phone_updated' => $familyPhoneUpdated,
                'family_phone_deleted' => $familyPhoneDeleted,
            ];
        });

        return redirect()
            ->route('system.payment-testers.index', ['q' => (string) $request->query('q', '')])
            ->with('status', sprintf(
                'Phone corrected successfully. Parent users: %d, students: %d, TAC logs: %d, login logs: %d, family phones updated: %d, duplicates removed: %d.',
                $summary['parent_users_updated'],
                $summary['students_updated'],
                $summary['otp_updated'],
                $summary['audit_updated'],
                $summary['family_phone_updated'],
                $summary['family_phone_deleted'],
            ));
    }

    private function resolvePortalTestDummyFamily(): FamilyBilling
    {
        return FamilyBilling::query()->firstOrCreate(
            [
                'family_code' => 'TEST-DUMMY-PORTAL',
                'billing_year' => 2099,
            ],
            [
                'fee_amount' => 100.00,
                'paid_amount' => 0,
                'status' => 'pending',
                'notes' => 'Dummy family for portal login invite testing. Use billing year 2099 so this record is not part of current operational statistics.',
            ]
        );
    }
}
