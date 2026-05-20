<?php

use App\Models\FamilyBilling;
use App\Models\ParentLoginAudit;
use App\Models\Student;
use App\Models\User;

it('allows system admin to view the parent access log with activity rows and summary cards', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'name' => 'Pn Noraini',
        'phone' => '0136544001',
        'email' => 'noraini@example.test',
        'email_verified_at' => now(),
    ]);

    Student::query()->create([
        'student_no' => '1A-0001',
        'family_code' => 'SSP-M001',
        'full_name' => 'Nur Aina',
        'class_name' => '1 AMANAH',
        'parent_name' => 'Pn Noraini',
        'parent_phone' => '0136544001',
        'parent_email' => 'noraini@example.test',
        'status' => 'active',
        'billing_year' => now()->year,
    ]);

    $family = FamilyBilling::query()->create([
        'family_code' => 'SSP-M001',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    ParentLoginAudit::query()->create([
        'user_id' => $parent->id,
        'phone' => '0136544001',
        'normalized_phone' => '60136544001',
        'action_type' => 'login',
        'access_status' => 'successful',
        'page_visited' => 'parent.login.verify.submit',
        'device_browser' => 'Mobile / Chrome',
        'family_billing_id' => $family->id,
        'logged_in_at' => now()->subHour(),
        'occurred_at' => now()->subHour(),
    ]);

    ParentLoginAudit::query()->create([
        'user_id' => $parent->id,
        'phone' => '0136544001',
        'normalized_phone' => '60136544001',
        'action_type' => 'viewed_dashboard',
        'access_status' => 'successful',
        'page_visited' => 'parent.dashboard',
        'device_browser' => 'Mobile / Chrome',
        'family_billing_id' => $family->id,
        'logged_in_at' => now(),
        'occurred_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('teacher.family-login-monitor'));

    $response->assertOk();
    $response->assertSee('Parent Access Log');
    $response->assertSee('Visits Today');
    $response->assertSee('PN NORAINI', false);
    $response->assertSee('viewed dashboard', false);
    $response->assertSee('1 AMANAH');
});

it('filters the parent access log to teacher plus parent users only', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $dualRoleParent = User::factory()->create([
        'role' => 'parent',
        'name' => 'Dual Role Parent',
        'phone' => '0191111222',
        'email_verified_at' => now(),
    ]);
    $dualRoleParent->assignRole('teacher');

    $parentOnly = User::factory()->create([
        'role' => 'parent',
        'name' => 'Parent Only',
        'phone' => '0193333444',
        'email_verified_at' => now(),
    ]);

    ParentLoginAudit::query()->create([
        'user_id' => $dualRoleParent->id,
        'phone' => '0191111222',
        'normalized_phone' => '60191111222',
        'action_type' => 'teacher_space_opened',
        'access_status' => 'successful',
        'page_visited' => 'portal-space.switch',
        'logged_in_at' => now(),
        'occurred_at' => now(),
    ]);

    ParentLoginAudit::query()->create([
        'user_id' => $parentOnly->id,
        'phone' => '0193333444',
        'normalized_phone' => '60193333444',
        'action_type' => 'login',
        'access_status' => 'successful',
        'page_visited' => 'parent.login.verify.submit',
        'logged_in_at' => now(),
        'occurred_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('teacher.family-login-monitor', [
        'role_mode' => 'teacher_parent',
    ]));

    $response->assertOk();
    $response->assertSee('DUAL ROLE PARENT');
    $response->assertDontSee('PARENT ONLY');
});

it('exports the parent access log as csv for system admin', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'name' => 'Csv Parent',
        'phone' => '0187788990',
        'email_verified_at' => now(),
    ]);

    ParentLoginAudit::query()->create([
        'user_id' => $parent->id,
        'phone' => '0187788990',
        'normalized_phone' => '60187788990',
        'action_type' => 'blocked_access',
        'access_status' => 'blocked',
        'page_visited' => 'parent.login.request',
        'logged_in_at' => now(),
        'occurred_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('teacher.family-login-monitor.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Parent Name');
    expect($response->streamedContent())->toContain('CSV PARENT');
});

it('blocks teacher role from viewing family login monitor', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.family-login-monitor'));

    $response->assertForbidden();
});

it('blocks parent role from viewing family login monitor', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($parent)->get(route('teacher.family-login-monitor'));

    $response->assertForbidden();
});
