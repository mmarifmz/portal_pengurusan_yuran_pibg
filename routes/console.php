<?php

use App\Services\LegacyFamilyBillingImporter;
use App\Services\WhatsAppTacSender;
use App\Models\AdminProvisionAudit;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:import-legacy-families {path : Full path to the legacy CSV file} {--legacy-year=2025 : Billing year stored for the imported legacy rows} {--current-year=2026 : Current-year student dataset used for matching} {--school-code=SSP : School prefix used when converting old family ids} {--dry-run : Show the match report without writing family billing rows}', function (LegacyFamilyBillingImporter $importer) {
    $report = $importer->import(
        (string) $this->argument('path'),
        (int) $this->option('legacy-year'),
        (int) $this->option('current-year'),
        (string) $this->option('school-code'),
        (bool) $this->option('dry-run'),
    );

    $this->info('Legacy family billing import summary');
    $this->line('Processed rows: '.$report['processed_rows']);
    $this->line('Matched rows: '.$report['matched_rows']);
    $this->line('Unmatched rows: '.$report['unmatched_rows']);
    $this->line('Ambiguous rows: '.$report['ambiguous_rows']);
    $this->line('Matched by student code: '.$report['matched_by_student_code']);
    $this->line('Matched by family code + name: '.$report['matched_by_family_code_and_name']);
    $this->line('Matched by unique name only: '.$report['matched_by_unique_name_only']);
    $this->line('Billing rows upserted: '.$report['billing_rows_upserted']);

    if ($report['families'] !== []) {
        $this->newLine();
        $this->table(
            ['Family Code', 'Legacy Family', 'Fee', 'Paid', 'Status', 'Match Methods'],
            array_map(fn (array $row) => [
                $row['family_code'],
                $row['legacy_family_code'],
                number_format((float) $row['fee_amount'], 2),
                number_format((float) $row['paid_amount'], 2),
                $row['status'],
                $row['match_methods'],
            ], $report['families'])
        );
    }

    if ($report['unmatched'] !== []) {
        $this->warn('Unmatched rows:');
        foreach (array_slice($report['unmatched'], 0, 10) as $row) {
            $this->line('- '.implode(' | ', array_filter([
                $row['family_id'] ? 'family='.$row['family_id'] : null,
                $row['student_name'] ? 'name='.$row['student_name'] : null,
                $row['student_code'] ? 'student_code='.$row['student_code'] : null,
            ])));
        }
    }

    if ($report['ambiguous'] !== []) {
        $this->warn('Ambiguous rows:');
        foreach (array_slice($report['ambiguous'], 0, 10) as $row) {
            $this->line('- '.implode(' | ', array_filter([
                $row['family_id'] ? 'family='.$row['family_id'] : null,
                $row['student_name'] ? 'name='.$row['student_name'] : null,
                $row['student_code'] ? 'student_code='.$row['student_code'] : null,
            ])));
        }
    }

    if ($report['dry_run']) {
        $this->comment('Dry run only: no database rows were written.');
    }
})->purpose('Import last-year family billing history by matching against the current-year student dataset.');

Artisan::command('system:admin:provision
    {--name= : Name for the admin account}
    {--email= : Email for the admin account}
    {--password= : Password for the admin account}
    {--approver-email= : Existing system_admin/system_installer email}
    {--approver-password= : Existing system_admin/system_installer password}
    {--installer-secret= : Bootstrap secret when no privileged user exists}', function () {
    $name = trim((string) ($this->option('name') ?? ''));
    $email = mb_strtolower(trim((string) ($this->option('email') ?? '')));
    $password = (string) ($this->option('password') ?? '');
    $approverEmail = mb_strtolower(trim((string) ($this->option('approver-email') ?? '')));
    $approverPassword = (string) ($this->option('approver-password') ?? '');
    $installerSecretInput = (string) ($this->option('installer-secret') ?? '');

    if ($name === '') {
        $name = trim((string) $this->ask('New admin name'));
    }

    if ($email === '') {
        $email = mb_strtolower(trim((string) $this->ask('New admin email')));
    }

    if ($password === '') {
        $password = (string) $this->secret('New admin password (min 8 chars)');
    }

    if ($name === '' || $email === '' || $password === '') {
        $this->error('Name, email, and password are required.');
        return self::FAILURE;
    }

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Invalid email format.');
        return self::FAILURE;
    }

    if (mb_strlen($password) < 8) {
        $this->error('Password must be at least 8 characters.');
        return self::FAILURE;
    }

    $privilegedRoles = ['system_admin', 'system_installer'];
    $privilegedExists = User::query()->whereIn('role', $privilegedRoles)->exists();
    $approver = null;
    $approvalMethod = 'installer_secret';

    if ($privilegedExists) {
        if ($approverEmail === '') {
            $approverEmail = mb_strtolower(trim((string) $this->ask('Approver email (system_admin/system_installer)')));
        }

        if ($approverPassword === '') {
            $approverPassword = (string) $this->secret('Approver password');
        }

        $approver = User::query()
            ->whereIn('role', $privilegedRoles)
            ->whereRaw('LOWER(email) = ?', [$approverEmail])
            ->first();

        if (! $approver || ! Hash::check($approverPassword, (string) $approver->password)) {
            $this->error('Authorization failed. Approver credentials are invalid.');
            return self::FAILURE;
        }

        $approvalMethod = 'privileged_credentials';
    } else {
        $configuredInstallerSecret = (string) config('services.system_installer_secret', '');
        if ($configuredInstallerSecret === '') {
            $this->error('SYSTEM_INSTALLER_SECRET is not configured. Bootstrap not allowed.');
            return self::FAILURE;
        }

        if ($installerSecretInput === '') {
            $installerSecretInput = (string) $this->secret('Installer secret');
        }

        if (! hash_equals($configuredInstallerSecret, $installerSecretInput)) {
            $this->error('Installer secret is invalid.');
            return self::FAILURE;
        }
    }

    $target = User::query()
        ->whereRaw('LOWER(email) = ?', [$email])
        ->first();

    $previousRole = $target?->role;
    $isCreate = $target === null;
    if (! $target) {
        $target = new User();
    }

    $target->name = $name;
    $target->email = $email;
    $target->role = 'system_admin';
    $target->class_name = null;
    $target->password = $password;
    $target->email_verified_at ??= now();
    $target->save();

    $provisionAction = $isCreate
        ? 'created_system_admin'
        : ($previousRole === 'system_admin'
            ? 'updated_system_admin'
            : 'promoted_to_system_admin');

    AdminProvisionAudit::create([
        'approver_user_id' => $approver?->id,
        'approver_email' => $approver?->email,
        'approval_method' => $approvalMethod,
        'target_user_id' => $target->id,
        'target_email' => $target->email,
        'target_name' => $target->name,
        'provision_action' => $provisionAction,
        'metadata' => [
            'previous_role' => $previousRole,
            'new_role' => 'system_admin',
            'executed_via' => 'artisan',
        ],
    ]);

    $this->info($isCreate
        ? "System admin created: {$target->email}"
        : "User promoted/updated as system_admin: {$target->email}");

    return self::SUCCESS;
})->purpose('Provision or promote a system_admin account via CLI with privileged authorization.');

Artisan::command('whatsapp:test
    {phone : Target phone number (e.g. 60123456789)}
    {--message= : Custom message text}
    {--tac : Send TAC template instead of custom message}
    {--family-code=TEST-FAMILY : Family code shown in TAC template}', function (WhatsAppTacSender $whatsAppTacSender) {
    $phone = trim((string) $this->argument('phone'));
    $message = trim((string) ($this->option('message') ?? ''));
    $sendTac = (bool) $this->option('tac');
    $familyCode = trim((string) ($this->option('family-code') ?? 'TEST-FAMILY'));

    if ($phone === '') {
        $this->error('Phone number is required.');

        return self::FAILURE;
    }

    try {
        $result = $sendTac
            ? $whatsAppTacSender->sendTac($phone, (string) random_int(100000, 999999), $familyCode !== '' ? $familyCode : 'TEST-FAMILY')
            : $whatsAppTacSender->sendMessage(
                $phone,
                $message !== '' ? $message : 'Ini mesej ujian WhatsApp dari Portal PIBG.'
            );
    } catch (\Throwable $exception) {
        $this->error('WhatsApp test failed: '.$exception->getMessage());

        return self::FAILURE;
    }

    $this->info('WhatsApp test sent.');
    $this->line('Provider: '.(string) ($result['provider'] ?? 'unknown'));
    $this->line('Status: '.(string) ($result['status'] ?? 'unknown'));
    $this->line('Message ID: '.(string) ($result['message_id'] ?? '-'));

    return self::SUCCESS;
})->purpose('Send a WhatsApp TAC/message test to verify provider delivery.');
