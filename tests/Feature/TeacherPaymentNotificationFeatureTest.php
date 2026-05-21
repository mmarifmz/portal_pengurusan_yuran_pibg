<?php

use App\Jobs\SendTeacherPaymentNotificationJob;
use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use App\Models\Student;
use App\Models\TeacherPaymentNotification;
use App\Models\User;
use App\Services\TeacherPaymentNotificationMessageBuilder;
use App\Services\WaSenderService;
use Illuminate\Support\Facades\Queue;

it('creates queued teacher payment notifications from the receipt page', function () {
    Queue::fake();

    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
        'email' => 'parent@example.test',
    ]);

    $teacher = User::factory()->create([
        'role' => 'teacher',
        'class_name' => '6 Bestari',
        'phone' => '0139906160',
        'receive_whatsapp_notifications' => true,
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F100',
        'billing_year' => 2026,
        'fee_amount' => 120,
        'paid_amount' => 120,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP1001',
        'family_code' => 'SSP-F100',
        'full_name' => 'Alya Zahra',
        'class_name' => '6 Bestari',
        'parent_name' => 'Pn. Huda',
        'parent_phone' => '0123456789',
        'parent_email' => 'parent@example.test',
        'billing_year' => 2026,
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'PBG-TEST-100',
        'amount' => 120,
        'fee_amount_paid' => 100,
        'donation_amount' => 20,
        'payer_email' => 'parent@example.test',
        'payer_phone' => '0123456789',
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $response = $this->actingAs($parent)
        ->postJson(route('receipts.share-to-teacher', $transaction->receipt_uuid));

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('duplicate', false);

    expect(TeacherPaymentNotification::query()->count())->toBe(1);

    Queue::assertPushed(SendTeacherPaymentNotificationJob::class);
    expect(TeacherPaymentNotification::query()->first()->status)->toBe(TeacherPaymentNotification::STATUS_QUEUED);
});

it('prevents duplicate queue creation within the same receipt share window', function () {
    Queue::fake();

    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123000000',
        'email' => 'parent2@example.test',
    ]);

    User::factory()->create([
        'role' => 'teacher',
        'class_name' => '5 Cekal',
        'phone' => '0139906170',
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F200',
        'billing_year' => 2026,
        'fee_amount' => 90,
        'paid_amount' => 90,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP2001',
        'family_code' => 'SSP-F200',
        'full_name' => 'Adam Danish',
        'class_name' => '5 Cekal',
        'parent_name' => 'Pn. Sara',
        'parent_phone' => '0123000000',
        'parent_email' => 'parent2@example.test',
        'billing_year' => 2026,
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'PBG-TEST-200',
        'amount' => 90,
        'fee_amount_paid' => 90,
        'donation_amount' => 0,
        'payer_email' => 'parent2@example.test',
        'payer_phone' => '0123000000',
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $this->actingAs($parent)->postJson(route('receipts.share-to-teacher', $transaction->receipt_uuid))->assertOk();
    $response = $this->actingAs($parent)->postJson(route('receipts.share-to-teacher', $transaction->receipt_uuid));

    $response
        ->assertOk()
        ->assertJsonPath('duplicate', true)
        ->assertJsonPath('message', 'Makluman ini telah berada dalam giliran penghantaran.');

    expect(TeacherPaymentNotification::query()->count())->toBe(1);
});

it('shows donation line only when donation amount is positive', function () {
    $builder = app(TeacherPaymentNotificationMessageBuilder::class);

    $withDonation = $builder->build([
        'teacher_name' => 'CIKGU AMIRA',
        'family_code' => 'SSP-F300',
        'bill_year' => '2026',
        'class_name' => '4 Intelek',
        'student_names' => ['Aisyah'],
        'order_id' => 'PBG-TEST-300',
        'receipt_url' => 'https://example.test/receipts/abc',
        'pibg_amount' => 60,
        'donation_amount' => 10,
        'total_amount' => 70,
        'is_instalment' => false,
        'is_payment_complete' => true,
    ]);

    $withoutDonation = $builder->build([
        'teacher_name' => 'CIKGU AMIRA',
        'family_code' => 'SSP-F300',
        'bill_year' => '2026',
        'class_name' => '4 Intelek',
        'student_names' => ['Aisyah'],
        'order_id' => 'PBG-TEST-300',
        'receipt_url' => 'https://example.test/receipts/abc',
        'pibg_amount' => 60,
        'donation_amount' => 0,
        'total_amount' => 60,
        'is_instalment' => false,
        'is_payment_complete' => true,
    ]);

    expect($withDonation)->toContain('Sumbangan Tambahan');
    expect($withoutDonation)->not->toContain('Sumbangan Tambahan');
});

it('marks failed send attempts as failed', function () {
    config()->set('services.wasender.api_key', 'test-api-key');
    config()->set('services.wasender.api_url', 'https://wa.example.test/api');
    config()->set('whatsapp.send_interval_seconds', 1);
    config()->set('whatsapp.account_protection_mode', false);
    config()->set('whatsapp.shared_session', false);

    $notification = TeacherPaymentNotification::query()->create([
        'teacher_name' => 'CIKGU RINA',
        'teacher_phone' => '01311223344',
        'class_name' => '6 Ikhlas',
        'order_id' => 'PBG-FAIL-1',
        'bill_year' => '2026',
        'receipt_url' => 'https://example.test/receipts/fail',
        'pibg_amount' => 80,
        'donation_amount' => 0,
        'total_amount' => 80,
        'message_body' => 'Fail test',
        'status' => TeacherPaymentNotification::STATUS_QUEUED,
        'idempotency_key' => sha1('teacher-fail'),
        'queued_at' => now(),
    ]);

    $mock = Mockery::mock(WaSenderService::class);
    $mock->shouldReceive('sendText')->once()->andThrow(new RuntimeException('Wasender WhatsApp send failed.'));
    app()->instance(WaSenderService::class, $mock);

    $job = new SendTeacherPaymentNotificationJob($notification->id);

    try {
        app()->call([$job, 'handle']);
    } catch (Throwable $throwable) {
        $job->failed($throwable);
    }

    $notification->refresh();

    expect($notification->status)->toBe(TeacherPaymentNotification::STATUS_FAILED);
    expect($notification->failed_at)->not->toBeNull();
});

it('allows admin retry to requeue failed teacher payment notifications', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => 'system_admin']);

    $notification = TeacherPaymentNotification::query()->create([
        'teacher_name' => 'CIKGU RINA',
        'teacher_phone' => '01311223344',
        'class_name' => '6 Ikhlas',
        'order_id' => 'PBG-RETRY-1',
        'bill_year' => '2026',
        'receipt_url' => 'https://example.test/receipts/retry',
        'pibg_amount' => 80,
        'donation_amount' => 5,
        'total_amount' => 85,
        'message_body' => 'Retry test',
        'status' => TeacherPaymentNotification::STATUS_FAILED,
        'attempt_count' => 3,
        'failed_at' => now(),
        'last_error' => 'API timeout',
        'idempotency_key' => sha1('teacher-retry'),
        'queued_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.whatsapp-queue.teacher-payment-notifications.retry', $notification));

    $response->assertRedirect();

    $notification->refresh();

    expect($notification->status)->toBe(TeacherPaymentNotification::STATUS_QUEUED);
    expect($notification->last_error)->toBeNull();
    Queue::assertPushed(SendTeacherPaymentNotificationJob::class);
});

it('loads the admin teacher payment notification page', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);

    TeacherPaymentNotification::query()->create([
        'teacher_name' => 'CIKGU FARAH',
        'teacher_phone' => '01355667788',
        'class_name' => '3 Dinamik',
        'order_id' => 'PBG-LIST-1',
        'bill_year' => '2026',
        'receipt_url' => 'https://example.test/receipts/list',
        'pibg_amount' => 50,
        'donation_amount' => 0,
        'total_amount' => 50,
        'message_body' => 'List test',
        'status' => TeacherPaymentNotification::STATUS_QUEUED,
        'idempotency_key' => sha1('teacher-list'),
        'queued_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.whatsapp-queue.teacher-payment-notifications.index'));

    $response
        ->assertOk()
        ->assertSee('Teacher Payment Notifications')
        ->assertSee('PBG-LIST-1');
});

it('returns a friendly error when teacher phone is missing', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123888888',
        'email' => 'parent3@example.test',
    ]);

    User::factory()->create([
        'role' => 'teacher',
        'class_name' => '2 Jujur',
        'phone' => '',
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F500',
        'billing_year' => 2026,
        'fee_amount' => 75,
        'paid_amount' => 75,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'SSP5001',
        'family_code' => 'SSP-F500',
        'full_name' => 'Nur Hani',
        'class_name' => '2 Jujur',
        'parent_name' => 'Pn. Laila',
        'parent_phone' => '0123888888',
        'parent_email' => 'parent3@example.test',
        'billing_year' => 2026,
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'PBG-TEST-500',
        'amount' => 75,
        'fee_amount_paid' => 75,
        'donation_amount' => 0,
        'payer_email' => 'parent3@example.test',
        'payer_phone' => '0123888888',
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $this->actingAs($parent)
        ->postJson(route('receipts.share-to-teacher', $transaction->receipt_uuid))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Nombor telefon guru kelas belum didaftarkan. Sila hubungi pihak sekolah.');
});

it('includes the split payment incomplete status section in the queued message', function () {
    Queue::fake();

    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123999999',
        'email' => 'parent4@example.test',
    ]);

    User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Bestari',
        'phone' => '0139090909',
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'SSP-F600',
        'billing_year' => 2026,
        'fee_amount' => 120,
        'paid_amount' => 40,
        'status' => 'pending',
    ]);

    Student::query()->create([
        'student_no' => 'SSP6001',
        'family_code' => 'SSP-F600',
        'full_name' => 'Irfan Hakim',
        'class_name' => '1 Bestari',
        'parent_name' => 'Pn. Mariam',
        'parent_phone' => '0123999999',
        'parent_email' => 'parent4@example.test',
        'billing_year' => 2026,
    ]);

    $plan = FamilyPaymentPlan::query()->create([
        'family_billing_id' => $billing->id,
        'plan_type' => FamilyPaymentPlan::PLAN_THREE_TIMES,
        'total_amount' => 120,
        'paid_amount' => 40,
        'balance_amount' => 80,
        'status' => FamilyPaymentPlan::STATUS_PARTIAL,
        'selected_at' => now(),
    ]);

    $installment = FamilyPaymentInstallment::query()->create([
        'family_payment_plan_id' => $plan->id,
        'family_billing_id' => $billing->id,
        'installment_no' => 1,
        'amount' => 40,
        'status' => FamilyPaymentInstallment::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $transaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'family_payment_installment_id' => $installment->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'PBG-TEST-600',
        'amount' => 40,
        'fee_amount_paid' => 40,
        'donation_amount' => 0,
        'payer_email' => 'parent4@example.test',
        'payer_phone' => '0123999999',
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $this->actingAs($parent)
        ->postJson(route('receipts.share-to-teacher', $transaction->receipt_uuid))
        ->assertOk();

    $message = (string) TeacherPaymentNotification::query()->first()?->message_body;

    expect($message)->toContain('STATUS BAYARAN');
    expect($message)->toContain('Bayaran ansuran telah diterima.');
});
