<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

it('syncs placeholder parent profiles from payment checkout payer data', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-SYNC1',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'PBG-TEST-SYNC-001',
        'amount' => 100,
        'fee_amount_paid' => 100,
        'payer_name' => 'Sharifah Zabedah Ainon Azim',
        'payer_email' => 'sharifahzabedah@gmail.com',
        'payer_phone' => '+60196156805',
        'status' => 'success',
        'return_status' => 'Successful',
        'paid_at' => now(),
    ]);

    Student::query()->create([
        'student_no' => 'SYNC-001',
        'family_code' => 'SSP-SYNC1',
        'full_name' => 'Anak Satu',
        'class_name' => '1 Angsana',
        'billing_year' => $billingYear,
        'parent_name' => '-',
        'parent_email' => 'parent-ssp-sync1@placeholder.local',
        'parent_phone' => '0196156805',
    ]);

    Student::query()->create([
        'student_no' => 'SYNC-002',
        'family_code' => 'SSP-SYNC1',
        'full_name' => 'Anak Dua',
        'class_name' => '3 Azalea',
        'billing_year' => $billingYear,
        'parent_name' => 'PARENT SSP-SYNC1',
        'parent_email' => 'parent-ssp-sync1-2@placeholder.local',
        'parent_phone' => '0196156805',
    ]);

    $parentUser = User::factory()->create([
        'role' => 'parent',
        'name' => '-',
        'email' => 'parent-ssp-sync1-0196156805@placeholder.local',
        'phone' => '0196156805',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)->post(route('teacher.records.parent-profile-sync'));

    $response->assertRedirect(route('teacher.records'));

    $updatedStudents = Student::query()
        ->where('family_code', 'SSP-SYNC1')
        ->get();

    expect($updatedStudents->pluck('parent_name')->unique()->all())
        ->toContain('SHARIFAH ZABEDAH AINON AZIM');
    expect($updatedStudents->pluck('parent_email')->unique()->all())
        ->toContain('sharifahzabedah@gmail.com');

    $parentUser->refresh();
    expect($parentUser->name)->toBe('SHARIFAH ZABEDAH AINON AZIM');
    expect($parentUser->email)->toBe('sharifahzabedah@gmail.com');
});

it('forbids non system admin from running parent profile sync', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($teacher)->post(route('teacher.records.parent-profile-sync'));

    $response->assertForbidden();
});
