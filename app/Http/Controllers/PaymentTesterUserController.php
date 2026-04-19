<?php

namespace App\Http\Controllers;

use App\Models\FamilyBillingPhone;
use App\Models\ParentLoginAudit;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use App\Models\User;
use App\Services\WhatsAppTacSender;
use App\Support\ParentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        return view('system.payment-testers', [
            'parentUsers' => $parentUsers,
            'keyword' => $keyword,
            'hasPaymentTesterColumn' => $hasPaymentTesterColumn,
            'defaultWhatsappTestPhone' => (string) config('services.treasury_whatsapp_phone', ''),
            'defaultWhatsappTestMessage' => 'Ini mesej ujian WhatsApp dari Portal PIBG.',
        ]);
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
}
