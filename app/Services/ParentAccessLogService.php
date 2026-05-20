<?php

namespace App\Services;

use App\Models\FamilyBilling;
use App\Models\ParentLoginAudit;
use App\Models\Student;
use App\Models\User;
use App\Support\ParentPhone;
use Illuminate\Http\Request;

class ParentAccessLogService
{
    public function log(Request $request, string $actionType, array $attributes = []): ParentLoginAudit
    {
        if (! ParentLoginAudit::tableIsAvailable()) {
            return new ParentLoginAudit();
        }

        /** @var User|null $user */
        $user = $attributes['user'] ?? $request->user();
        $phone = ParentPhone::sanitizeInput((string) ($attributes['phone'] ?? $user?->phone ?? ''));
        $occurredAt = $attributes['occurred_at'] ?? now();

        $familyBilling = $attributes['family_billing'] ?? null;
        $student = $attributes['student'] ?? null;

        $payload = [
            'user_id' => $user?->id,
            'phone' => $phone,
            'normalized_phone' => ParentPhone::normalizeForMatch($phone),
            'logged_in_at' => $occurredAt,
        ];

        if (ParentLoginAudit::hasAuditColumn('action_type')) {
            $payload['action_type'] = $actionType;
        }

        if (ParentLoginAudit::hasAuditColumn('access_status')) {
            $payload['access_status'] = (string) ($attributes['access_status'] ?? $this->defaultStatusForAction($actionType));
        }

        if (ParentLoginAudit::hasAuditColumn('page_visited')) {
            $payload['page_visited'] = (string) ($attributes['page_visited'] ?? $request->route()?->getName() ?? $request->path());
        }

        if (ParentLoginAudit::hasAuditColumn('login_method')) {
            $payload['login_method'] = $attributes['login_method'] ?? $request->session()->get('parent_login_method');
        }

        if (ParentLoginAudit::hasAuditColumn('ip_address')) {
            $payload['ip_address'] = $request->ip();
        }

        if (ParentLoginAudit::hasAuditColumn('user_agent')) {
            $payload['user_agent'] = $request->userAgent();
        }

        if (ParentLoginAudit::hasAuditColumn('device_browser')) {
            $payload['device_browser'] = $this->deviceBrowserSummary($request->userAgent());
        }

        if (ParentLoginAudit::hasAuditColumn('family_billing_id')) {
            $payload['family_billing_id'] = $familyBilling instanceof FamilyBilling ? $familyBilling->id : ($attributes['family_billing_id'] ?? null);
        }

        if (ParentLoginAudit::hasAuditColumn('student_id')) {
            $payload['student_id'] = $student instanceof Student ? $student->id : ($attributes['student_id'] ?? null);
        }

        if (ParentLoginAudit::hasAuditColumn('space_key')) {
            $payload['space_key'] = $attributes['space_key'] ?? $request->session()->get('active_portal_space');
        }

        if (ParentLoginAudit::hasAuditColumn('occurred_at')) {
            $payload['occurred_at'] = $occurredAt;
        }

        if (ParentLoginAudit::hasAuditColumn('meta')) {
            $payload['meta'] = $attributes['meta'] ?? null;
        }

        return ParentLoginAudit::query()->create($payload);
    }

    private function defaultStatusForAction(string $actionType): string
    {
        return match ($actionType) {
            'failed_access' => 'failed',
            'blocked_access' => 'blocked',
            default => 'successful',
        };
    }

    private function deviceBrowserSummary(?string $userAgent): ?string
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return null;
        }

        $browser = 'Browser';
        foreach ([
            'Edg/' => 'Edge',
            'OPR/' => 'Opera',
            'Chrome/' => 'Chrome',
            'Firefox/' => 'Firefox',
            'Safari/' => 'Safari',
        ] as $needle => $label) {
            if (str_contains($ua, $needle)) {
                $browser = $label;
                break;
            }
        }

        $device = str_contains($ua, 'Mobile') ? 'Mobile' : 'Desktop';
        if (str_contains($ua, 'Tablet') || str_contains($ua, 'iPad')) {
            $device = 'Tablet';
        }

        return "{$device} / {$browser}";
    }
}
