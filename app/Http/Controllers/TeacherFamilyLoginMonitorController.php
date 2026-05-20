<?php

namespace App\Http\Controllers;

use App\Models\FamilyBilling;
use App\Models\ParentLoginAudit;
use App\Models\ParentLoginInvite;
use App\Models\ParentLoginOtp;
use App\Models\Student;
use App\Models\User;
use App\Services\ParentAccountService;
use App\Services\WhatsAppTacSender;
use App\Support\ParentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherFamilyLoginMonitorController extends Controller
{
    public function __construct(
        private readonly WhatsAppTacSender $whatsAppTacSender,
        private readonly ParentAccountService $parentAccountService
    )
    {
    }

    public function index(Request $request): View
    {
        $dataset = $this->buildDataset($request);

        return view('teacher.family-login-monitor', $dataset);
    }

    public function export(Request $request): StreamedResponse
    {
        $dataset = $this->buildDataset($request);
        $rows = $dataset['rows'];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Parent Name',
                'Phone / Email',
                'Linked Students',
                'Class',
                'Page Visited',
                'Action Type',
                'Access Status',
                'IP Address',
                'Device / Browser',
                'Login Method',
                'Roles',
                'Created At',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['parent_name'],
                    trim($row['phone'].' / '.$row['email'], ' /'),
                    $row['students_display'],
                    $row['class_display'],
                    $row['page_visited'],
                    $row['action_type'],
                    $row['access_status'],
                    $row['ip_address'],
                    $row['device_browser'],
                    $row['login_method'],
                    $row['roles_display'],
                    $row['occurred_at']?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 'parent-access-log.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
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
            ->withRole('parent')
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
        return $this->parentAccountService->resolveOrCreateForFamily($phone, $familyBilling);
    }

    /**
     * @return array{
     *   rows: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *   generatedAt: \Illuminate\Support\Carbon,
     *   classOptions: \Illuminate\Support\Collection<int, string>,
     *   search: string,
     *   selectedClass: string,
     *   selectedAction: string,
     *   selectedAccess: string,
     *   selectedRoleMode: string,
     *   dateFromInput: string,
     *   dateToInput: string,
     *   summary: array<string, mixed>
     * }
     */
    private function buildDataset(Request $request): array
    {
        $search = trim((string) $request->string('q')->toString());
        $selectedClass = trim((string) $request->string('class_name')->toString());
        $selectedAction = trim((string) $request->string('action_type', 'all')->toString());
        $selectedAccess = trim((string) $request->string('access_filter', 'all')->toString());
        $selectedRoleMode = trim((string) $request->string('role_mode', 'all')->toString());
        $dateFromInput = trim((string) $request->string('date_from')->toString());
        $dateToInput = trim((string) $request->string('date_to')->toString());

        $dateFrom = $dateFromInput !== '' ? rescue(fn () => Carbon::parse($dateFromInput)->startOfDay(), null, false) : null;
        $dateTo = $dateToInput !== '' ? rescue(fn () => Carbon::parse($dateToInput)->endOfDay(), null, false) : null;
        $todayStart = now()->startOfDay();
        $thirtyDaysAgo = now()->subDays(30);

        $classOptions = Student::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name')
            ->values();

        $rows = ParentLoginAudit::tableIsAvailable()
            ? ParentLoginAudit::orderByMostRecent(
                ParentLoginAudit::query()->with([
                    'user.roles',
                    'familyBilling.students',
                    'student',
                ])
            )->get()->map(function (ParentLoginAudit $audit): array {
                $user = $audit->user;
                $students = $audit->familyBilling?->students
                    ?? ($audit->student ? collect([$audit->student]) : ($user ? $this->parentAccountService->resolvedLinkedStudents($user) : collect()));

                $roles = $user?->roleNames() ?? [];
                $parentName = $user?->name
                    ?: (string) ($students->pluck('parent_name')->filter()->first() ?: '-');
                $email = (string) ($user?->email ?: $students->pluck('parent_email')->filter()->first() ?: '');
                $classDisplay = $students
                    ->pluck('class_name')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->implode(', ');

                return [
                    'parent_name' => $parentName,
                    'phone' => (string) ($audit->phone ?: $user?->phone ?: ''),
                    'email' => $email,
                    'students_display' => $students->pluck('full_name')->filter()->unique()->implode(', '),
                    'class_display' => $classDisplay,
                    'class_names' => $students->pluck('class_name')->filter()->unique()->values(),
                    'page_visited' => (string) ($audit->page_visited ?: '-'),
                    'action_type' => (string) ($audit->action_type ?: 'login'),
                    'access_status' => (string) ($audit->access_status ?: 'successful'),
                    'ip_address' => (string) ($audit->ip_address ?: '-'),
                    'device_browser' => (string) ($audit->device_browser ?: '-'),
                    'login_method' => (string) ($audit->login_method ?: '-'),
                    'occurred_at' => $audit->occurred_at_for_display,
                    'roles' => $roles,
                    'roles_display' => collect($roles)
                        ->map(fn (string $role): string => strtoupper(str_replace('_', ' ', $role === 'system_admin' ? 'admin' : $role)))
                        ->implode(', '),
                    'is_teacher_parent' => in_array('parent', $roles, true) && count($roles) > 1,
                ];
            })
            : collect();

        $filteredRows = $rows
            ->when($search !== '', function (Collection $collection) use ($search): Collection {
                $needle = mb_strtolower($search);

                return $collection->filter(function (array $row) use ($needle): bool {
                    return str_contains(mb_strtolower($row['parent_name']), $needle)
                        || str_contains(mb_strtolower($row['phone']), $needle)
                        || str_contains(mb_strtolower($row['email']), $needle)
                        || str_contains(mb_strtolower($row['students_display']), $needle);
                });
            })
            ->when($selectedClass !== '', fn (Collection $collection) => $collection->filter(
                fn (array $row): bool => $row['class_names']->contains($selectedClass)
            ))
            ->when($selectedAction !== '' && $selectedAction !== 'all', fn (Collection $collection) => $collection->where('action_type', $selectedAction))
            ->when($selectedAccess !== '' && $selectedAccess !== 'all', fn (Collection $collection) => $collection->where('access_status', $selectedAccess))
            ->when($selectedRoleMode === 'teacher_parent', fn (Collection $collection) => $collection->where('is_teacher_parent', true))
            ->when($dateFrom !== null, fn (Collection $collection) => $collection->filter(
                fn (array $row): bool => $row['occurred_at'] !== null && $row['occurred_at']->greaterThanOrEqualTo($dateFrom)
            ))
            ->when($dateTo !== null, fn (Collection $collection) => $collection->filter(
                fn (array $row): bool => $row['occurred_at'] !== null && $row['occurred_at']->lessThanOrEqualTo($dateTo)
            ))
            ->values();

        $todayRows = $rows->filter(
            fn (array $row): bool => $row['occurred_at'] !== null && $row['occurred_at']->greaterThanOrEqualTo($todayStart)
        );

        $activeParentsLast30Days = collect();
        if (ParentLoginAudit::tableIsAvailable()) {
            $activeParentsQuery = ParentLoginAudit::query()
                ->where(ParentLoginAudit::occurrenceColumn(), '>=', $thirtyDaysAgo)
                ->whereNotNull('user_id');

            if (ParentLoginAudit::hasAuditColumn('action_type')) {
                $activeParentsQuery->where(function ($query): void {
                    $query->whereNull('action_type')
                        ->orWhere('action_type', '!=', 'blocked_access');
                });
            }

            if (ParentLoginAudit::hasAuditColumn('access_status')) {
                $activeParentsQuery->where('access_status', 'successful');
            }

            $activeParentsLast30Days = $activeParentsQuery
                ->distinct()
                ->pluck('user_id');
        }

        $summary = [
            'total_parent_visits_today' => $todayRows->where('access_status', 'successful')->count(),
            'unique_parents_active_today' => $todayRows->where('access_status', 'successful')->pluck('phone')->filter()->unique()->count(),
            'blocked_access_attempts' => $todayRows->where('access_status', 'blocked')->count(),
            'most_active_class' => (string) ($todayRows
                ->flatMap(fn (array $row) => $row['class_names']->all())
                ->filter()
                ->countBy()
                ->sortDesc()
                ->keys()
                ->first() ?? '-'),
            'parents_not_active_30_days' => User::query()
                ->withRole('parent')
                ->get()
                ->reject(fn (User $user): bool => $activeParentsLast30Days->contains($user->id))
                ->count(),
        ];

        return [
            'rows' => $filteredRows,
            'generatedAt' => now(),
            'classOptions' => $classOptions,
            'search' => $search,
            'selectedClass' => $selectedClass,
            'selectedAction' => $selectedAction,
            'selectedAccess' => $selectedAccess,
            'selectedRoleMode' => $selectedRoleMode,
            'dateFromInput' => $dateFromInput,
            'dateToInput' => $dateToInput,
            'summary' => $summary,
            'actionOptions' => collect([
                'login',
                'logout',
                'viewed_dashboard',
                'viewed_payment',
                'clicked_pay_now',
                'viewed_receipt',
                'downloaded_receipt',
                'changed_payment_option',
                'failed_access',
                'blocked_access',
                'teacher_space_opened',
                'parent_space_opened',
            ]),
        ];
    }
}
