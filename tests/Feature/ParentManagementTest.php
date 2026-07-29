<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentInstallment;
use App\Models\FamilyPaymentPlan;
use App\Models\FamilyPaymentTransaction;
use App\Models\ParentStudentLink;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\User;
use App\Models\UserChangeAudit;
use App\Services\WhatsAppTacSender;

it('allows system admin to autosave parent access status and audit the change', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->patchJson(route('teacher.parent-management.settings.autosave', $parent), [
        'is_active' => false,
        'access_block_reason' => 'Blocked after duplicate access report.',
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'saved']);

    expect($parent->fresh()->is_active)->toBeFalse();
    expect($parent->fresh()->access_block_reason)->toBe('Blocked after duplicate access report.');
    expect(UserChangeAudit::query()->where('affected_user_id', $parent->id)->where('field_changed', 'is_active')->exists())->toBeTrue();
});

it('allows system admin to autosave dual role assignment for a parent user', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
    ]);

    $response = $this->actingAs($admin)->patchJson(route('teacher.parent-management.settings.autosave', $parent), [
        'roles' => ['parent', 'teacher'],
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'saved']);

    expect($parent->fresh()->hasRole('parent'))->toBeTrue();
    expect($parent->fresh()->hasRole('teacher'))->toBeTrue();
    expect(UserChangeAudit::query()->where('affected_user_id', $parent->id)->where('field_changed', 'roles')->exists())->toBeTrue();
});

it('forbids admin alias from parent management autosave', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patchJson(route('teacher.parent-management.settings.autosave', $parent), [
            'is_active' => false,
        ])
        ->assertForbidden();
});

it('forbids parent from accessing other family billing records', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
    ]);

    $ownedStudent = Student::query()->create([
        'student_no' => '1A-0001',
        'family_code' => 'FAM-OWN',
        'full_name' => 'Anak Sendiri',
        'class_name' => '1 AMANAH',
        'parent_name' => 'Parent',
        'parent_phone' => '0123456789',
        'status' => 'active',
        'billing_year' => now()->year,
    ]);

    ParentStudentLink::query()->create([
        'user_id' => $parent->id,
        'student_id' => $ownedStudent->id,
        'relationship_type' => 'guardian',
        'linked_at' => now(),
    ]);

    $ownedBilling = FamilyBilling::query()->create([
        'family_code' => 'FAM-OWN',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $otherBilling = FamilyBilling::query()->create([
        'family_code' => 'FAM-OTHER',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $this->actingAs($parent)
        ->withSession([
            'parent_child_selection_completed' => true,
            'parent_selected_family_billing_id' => $otherBilling->id,
        ])
        ->get(route('parent.payments.checkout', $otherBilling))
        ->assertForbidden();

    $this->actingAs($parent)
        ->withSession([
            'parent_child_selection_completed' => true,
            'parent_selected_family_billing_id' => $ownedBilling->id,
        ])
        ->get(route('parent.payments.checkout', $ownedBilling))
        ->assertOk();
});

it('forbids teacher from parent payment history', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
    ]);

    $this->actingAs($teacher)
        ->get(route('parent.payments.history'))
        ->assertForbidden();
});

it('allows system admin to complete a verified payment manually with an audit trail', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'name' => 'Verified Parent',
        'phone' => '0123456789',
        'email' => 'parent@example.test',
    ]);

    $student = Student::query()->create([
        'student_no' => '1A-0099',
        'family_code' => 'FAM-MANUAL',
        'full_name' => 'Manual Payment Child',
        'class_name' => '1 AMANAH',
        'parent_name' => 'Verified Parent',
        'parent_phone' => '0123456789',
        'status' => 'active',
        'billing_year' => now()->year,
    ]);

    ParentStudentLink::query()->create([
        'user_id' => $parent->id,
        'student_id' => $student->id,
        'relationship_type' => 'guardian',
        'linked_at' => now(),
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'FAM-MANUAL',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 40,
        'status' => 'partial',
    ]);

    $plan = FamilyPaymentPlan::query()->create([
        'family_billing_id' => $billing->id,
        'plan_type' => FamilyPaymentPlan::PLAN_TWO_TIMES,
        'total_amount' => 100,
        'paid_amount' => 40,
        'balance_amount' => 60,
        'status' => FamilyPaymentPlan::STATUS_PARTIAL,
        'selected_at' => now(),
    ]);

    FamilyPaymentInstallment::query()->create([
        'family_payment_plan_id' => $plan->id,
        'family_billing_id' => $billing->id,
        'installment_no' => 1,
        'amount' => 40,
        'status' => FamilyPaymentInstallment::STATUS_PAID,
        'paid_at' => now()->subMonth(),
    ]);

    FamilyPaymentInstallment::query()->create([
        'family_payment_plan_id' => $plan->id,
        'family_billing_id' => $billing->id,
        'installment_no' => 2,
        'amount' => 60,
        'status' => FamilyPaymentInstallment::STATUS_REDIRECTED,
    ]);

    $pendingTransaction = FamilyPaymentTransaction::query()->create([
        'family_billing_id' => $billing->id,
        'user_id' => $parent->id,
        'payment_provider' => 'toyyibpay',
        'external_order_id' => 'PBG-PENDING-MANUAL',
        'amount' => 60,
        'fee_amount_paid' => 60,
        'status' => 'pending',
    ]);

    PaymentAllocation::query()->create([
        'family_payment_transaction_id' => $pendingTransaction->id,
        'family_billing_id' => $billing->id,
        'order_id' => $pendingTransaction->external_order_id,
        'allocation_type' => PaymentAllocation::TYPE_YURAN,
        'amount' => 60,
        'status' => PaymentAllocation::STATUS_PENDING,
    ]);

    $sender = Mockery::mock(WhatsAppTacSender::class);
    $sender->shouldReceive('sendMessage')
        ->once()
        ->with('0123456789', Mockery::on(function (string $message): bool {
            return str_contains($message, 'Pembayaran PIBG anda telah berjaya diterima.')
                && str_contains($message, '• Kod Keluarga: FAM-MANUAL')
                && str_contains($message, '• Jumlah: RM60.00')
                && str_contains($message, 'Resit Web:')
                && str_contains($message, '/receipts/');
        }))
        ->andReturn([
            'status' => 'success',
            'message_id' => 'MANUAL-RECEIPT-001',
        ]);
    $this->app->instance(WhatsAppTacSender::class, $sender);

    $this->actingAs($admin)
        ->get(route('teacher.parent-management.show', $parent))
        ->assertOk()
        ->assertSee('FAM-MANUAL')
        ->assertSee('Outstanding RM 60.00')
        ->assertSee('Mark payment complete manually')
        ->assertSee('RM 60.00');

    $paidAt = now()->subMinutes(15)->format('Y-m-d H:i:s');

    $response = $this->actingAs($admin)->post(
        route('teacher.parent-management.payments.complete', [$parent, $billing]),
        [
            'paid_at' => $paidAt,
            'payment_reference' => 'TP2606234794216255',
            'verification_note' => 'Verified against the receipt shared through WhatsApp.',
            'verified' => '1',
        ]
    );

    $response->assertRedirect(route('teacher.parent-management.show', $parent));
    $response->assertSessionHas('status', fn (string $status): bool => str_contains(
        $status,
        'A WhatsApp confirmation with the receipt link was sent to the registered parent.'
    ));

    $billing->refresh();
    expect((float) $billing->paid_amount)->toBe(100.0)
        ->and($billing->status)->toBe('paid');

    $manualTransaction = FamilyPaymentTransaction::query()
        ->where('family_billing_id', $billing->id)
        ->where('payment_provider', 'manual')
        ->sole();

    expect((float) $manualTransaction->amount)->toBe(60.0)
        ->and((float) $manualTransaction->fee_amount_paid)->toBe(60.0)
        ->and($manualTransaction->status)->toBe('success')
        ->and($manualTransaction->provider_ref_no)->toBe('TP2606234794216255')
        ->and($manualTransaction->raw_return['admin_user_id'])->toBe($admin->id)
        ->and($manualTransaction->receipt_message_id)->toBe('MANUAL-RECEIPT-001')
        ->and($manualTransaction->receipt_notified_at)->not->toBeNull();

    expect($manualTransaction->allocations()->where('status', PaymentAllocation::STATUS_PAID)->exists())->toBeTrue()
        ->and($pendingTransaction->fresh()->status)->toBe('superseded')
        ->and($pendingTransaction->allocations()->first()->status)->toBe(PaymentAllocation::STATUS_CANCELLED)
        ->and($plan->fresh()->status)->toBe(FamilyPaymentPlan::STATUS_PAID)
        ->and($plan->installments()->where('status', '!=', FamilyPaymentInstallment::STATUS_PAID)->exists())->toBeFalse();

    $audit = UserChangeAudit::query()
        ->where('affected_user_id', $parent->id)
        ->where('field_changed', 'manual_payment_completed')
        ->sole();

    expect($audit->admin_user_id)->toBe($admin->id)
        ->and($audit->meta['family_billing_id'])->toBe($billing->id)
        ->and($audit->meta['payment_reference'])->toBe('TP2606234794216255');

    $this->actingAs($admin)
        ->get(route('teacher.parent-management.show', $parent))
        ->assertOk()
        ->assertDontSee('Mark payment complete manually');
});

it('does not create a second manual transaction for an already paid family', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
    ]);

    $student = Student::query()->create([
        'student_no' => '1A-0100',
        'family_code' => 'FAM-PAID',
        'full_name' => 'Already Paid Child',
        'class_name' => '1 AMANAH',
        'parent_name' => $parent->name,
        'parent_phone' => $parent->phone,
        'status' => 'active',
        'billing_year' => now()->year,
    ]);

    ParentStudentLink::query()->create([
        'user_id' => $parent->id,
        'student_id' => $student->id,
        'relationship_type' => 'guardian',
        'linked_at' => now(),
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'FAM-PAID',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    $this->actingAs($admin)->post(
        route('teacher.parent-management.payments.complete', [$parent, $billing]),
        [
            'paid_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'payment_reference' => 'DUPLICATE-CHECK',
            'verification_note' => 'Attempted duplicate manual completion.',
            'verified' => '1',
        ]
    )->assertSessionHasErrors('manual_payment');

    expect(FamilyPaymentTransaction::query()->where('family_billing_id', $billing->id)->exists())->toBeFalse();
});

it('forbids non-system-admin users from completing a payment manually', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
    ]);

    $billing = FamilyBilling::query()->create([
        'family_code' => 'FAM-FORBIDDEN',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $this->actingAs($teacher)->post(
        route('teacher.parent-management.payments.complete', [$parent, $billing]),
        [
            'paid_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'payment_reference' => 'FORBIDDEN',
            'verification_note' => 'This must not be accepted.',
            'verified' => '1',
        ]
    )->assertForbidden();
});
