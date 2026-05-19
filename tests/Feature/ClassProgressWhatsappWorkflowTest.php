<?php

use App\Models\FamilyBilling;
use App\Models\LegacyStudentPayment;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppMessageQueue;
use App\Services\PaymentReportingService;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('class whatsapp preview returns 3 message parts', function () {
    [, , $admin] = seedClassProgressWhatsappDataset();

    $response = $this->actingAs($admin)->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year);

    $response
        ->assertOk()
        ->assertJsonPath('class_name', '1 Angsana')
        ->assertJsonPath('teacher_name', 'TEACHER ANGSANA');

    $parts = collect($response->json('generated_messages'))->pluck('message_part')->unique()->values()->all();
    expect($parts)->toBe(['summary', 'paid_list', 'unpaid_list']);
    expect($response->json('generated_messages.0.body'))->toContain('📊 *Ringkasan Yuran & Sumbangan PIBG*');
    expect($response->json('generated_messages.1.body'))->toContain('✅ *1 Angsana - Senarai Telah Bayar*');
    expect($response->json('generated_messages.2.body'))->toContain('⏳ *1 Angsana - Senarai Belum Bayar*');
});

it('missing teacher disables queue', function () {
    [, , $admin] = seedClassProgressWhatsappDataset(includeTeacherForAlamanda: false);

    $response = $this->actingAs($admin)->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Alamanda']).'?billing_year='.now()->year);

    $response
        ->assertOk()
        ->assertJsonPath('queue_eligibility.ready', false)
        ->assertJsonPath('queue_eligibility.status', 'missing_teacher');
});

it('missing phone disables queue', function () {
    [, , $admin] = seedClassProgressWhatsappDataset(includePhoneForAlamandaTeacher: false);

    $response = $this->actingAs($admin)->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Alamanda']).'?billing_year='.now()->year);

    $response
        ->assertOk()
        ->assertJsonPath('queue_eligibility.ready', false)
        ->assertJsonPath('queue_eligibility.status', 'missing_phone');
});

it('queue endpoint creates pending records', function () {
    [, , $admin] = seedClassProgressWhatsappDataset();

    $preview = $this->actingAs($admin)
        ->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->assertOk()
        ->json();

    $queueResponse = $this->actingAs($admin)->postJson(route('admin.classes.whatsapp-queue', ['class' => '1 Angsana']), [
        'billing_year' => now()->year,
        'preview_token' => $preview['preview_token'],
    ]);

    $queueResponse->assertOk();

    $messages = WhatsAppMessageQueue::query()
        ->where('class_name', '1 Angsana')
        ->orderBy('part_order')
        ->get();

    expect($messages)->toHaveCount(3);
    expect($messages->where('status', 'pending'))->toHaveCount(3);
    expect($messages->pluck('scheduled_at')->filter()->count())->toBe(3);
    expect($messages[0]->scheduled_at?->lt($messages[1]->scheduled_at))->toBeTrue();
    expect($messages[1]->scheduled_at?->lt($messages[2]->scheduled_at))->toBeTrue();
});

it('single class report respects existing pending queue schedule', function () {
    [, , $admin] = seedClassProgressWhatsappDataset();

    WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '0 Existing',
        'recipient_name' => 'Existing Queue',
        'recipient_phone' => '+60112223333',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Existing pending message',
        'status' => WhatsAppMessageQueue::STATUS_PENDING,
        'queued_by' => $admin->id,
        'queued_at' => now(),
        'scheduled_at' => now()->addMinutes(2),
        'total_parts' => 1,
        'part_order' => 1,
    ]);

    $preview = $this->actingAs($admin)
        ->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->assertOk()
        ->json();

    $this->actingAs($admin)->postJson(route('admin.classes.whatsapp-queue', ['class' => '1 Angsana']), [
        'billing_year' => now()->year,
        'preview_token' => $preview['preview_token'],
    ])->assertOk();

    $firstScheduledAt = WhatsAppMessageQueue::query()
        ->where('class_name', '1 Angsana')
        ->orderBy('part_order')
        ->value('scheduled_at');

    expect($firstScheduledAt)->not->toBeNull();
    expect($firstScheduledAt->gt(now()->addMinutes(2)))->toBeTrue();
});

it('duplicate recent queue warning works', function () {
    [, , $admin] = seedClassProgressWhatsappDataset();

    $firstPreview = $this->actingAs($admin)
        ->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->json();

    $this->actingAs($admin)->postJson(route('admin.classes.whatsapp-queue', ['class' => '1 Angsana']), [
        'billing_year' => now()->year,
        'preview_token' => $firstPreview['preview_token'],
    ])->assertOk();

    $secondPreview = $this->actingAs($admin)
        ->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->json();

    $duplicateResponse = $this->actingAs($admin)->postJson(route('admin.classes.whatsapp-queue', ['class' => '1 Angsana']), [
        'billing_year' => now()->year,
        'preview_token' => $secondPreview['preview_token'],
    ]);

    $duplicateResponse
        ->assertStatus(409)
        ->assertJsonPath('requires_confirmation', true);
});

it('batch preview identifies eligible and skipped classes', function () {
    [, , $admin] = seedClassProgressWhatsappDataset(includeTeacherForAzalea: false, includePhoneForAlamandaTeacher: false);

    $response = $this->actingAs($admin)->getJson(route('admin.classes.whatsapp-batch-preview').'?billing_year='.now()->year);

    $response->assertOk();
    $cards = collect($response->json('class_previews'))->keyBy('class_name');

    expect($cards->get('1 Angsana')['status'])->toBe('ready');
    expect($cards->get('1 Alamanda')['status'])->toBe('missing_phone');
    expect($cards->get('1 Azalea')['status'])->toBe('missing_teacher');
    expect($response->json('queue_schedule.estimated_total_messages'))->toBe(3);
});

it('batch queue creates queue records only for selected eligible classes', function () {
    [, , $admin] = seedClassProgressWhatsappDataset(includeTeacherForAzalea: false, includePhoneForAlamandaTeacher: false);

    $preview = $this->actingAs($admin)
        ->getJson(route('admin.classes.whatsapp-batch-preview').'?billing_year='.now()->year)
        ->assertOk()
        ->json();

    $queueResponse = $this->actingAs($admin)->postJson(route('admin.classes.whatsapp-batch-queue'), [
        'billing_year' => now()->year,
        'preview_token' => $preview['preview_token'],
        'class_names' => ['1 Angsana'],
    ]);

    $queueResponse->assertOk();

    expect(WhatsAppMessageQueue::query()->where('class_name', '1 Angsana')->count())->toBe(3);
    expect(WhatsAppMessageQueue::query()->where('class_name', '1 Alamanda')->count())->toBe(0);
    expect(WhatsAppMessageQueue::query()->where('class_name', '1 Azalea')->count())->toBe(0);
});

it('batch blast creates scheduled_at sequentially', function () {
    [, , $admin] = seedClassProgressWhatsappDataset();

    $preview = $this->actingAs($admin)
        ->getJson(route('admin.classes.whatsapp-batch-preview').'?billing_year='.now()->year)
        ->assertOk()
        ->json();

    $this->actingAs($admin)->postJson(route('admin.classes.whatsapp-batch-queue'), [
        'billing_year' => now()->year,
        'preview_token' => $preview['preview_token'],
        'class_names' => ['1 Alamanda', '1 Angsana', '1 Azalea'],
    ])->assertOk();

    $messages = WhatsAppMessageQueue::query()
        ->orderBy('part_order')
        ->get();

    expect($messages)->toHaveCount(9);
    expect($messages->pluck('part_order')->all())->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    expect($messages[0]->scheduled_at?->lt($messages[1]->scheduled_at))->toBeTrue();
    expect($messages[1]->scheduled_at?->lt($messages[2]->scheduled_at))->toBeTrue();
    expect($messages[2]->scheduled_at?->lt($messages[3]->scheduled_at))->toBeTrue();
});

it('message stats match leaderboard service', function () {
    [, , $admin] = seedClassProgressWhatsappDataset();

    $preview = $this->actingAs($admin)
        ->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->assertOk()
        ->json();

    $leaderboardRow = app(PaymentReportingService::class)
        ->classLeaderboard((int) now()->year)
        ->firstWhere('class_name', '1 Angsana');

    expect((float) $preview['class_stats']['payment_percentage'])->toBe((float) $leaderboardRow['completion_percent']);
    expect((float) $preview['class_stats']['total_collected'])->toBe((float) $leaderboardRow['jumlah_kutipan']);
    expect((float) $preview['class_stats']['pibg_amount'])->toBe((float) $leaderboardRow['yuran_collected']);
});

it('teacher can load own class details with previous year payer marker', function () {
    [$teacher] = seedClassProgressWhatsappDataset();

    FamilyBilling::query()->create([
        'family_code' => 'SSP-CW2',
        'billing_year' => now()->year - 1,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'SSP-CW2',
        'billing_year' => now()->year - 2,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    $response = $this->actingAs($teacher)->getJson(route('teacher.class-progress.details', ['class' => '1 Angsana']).'?billing_year='.now()->year);

    $response->assertOk()
        ->assertJsonPath('summary.class_name', '1 Angsana')
        ->assertJsonPath('summary.is_my_class', true)
        ->assertJsonPath('can_view_full_details', true)
        ->assertJsonPath('summary_only', false);

    $paidEntries = collect($response->json('paid_entries'));
    $unpaidEntries = collect($response->json('unpaid_entries'));

    expect($paidEntries->count())->toBe(1);
    expect($unpaidEntries->count())->toBe(1);
    expect((bool) $unpaidEntries->firstWhere('family_code', 'SSP-CW2')['previous_year_paid'])->toBeTrue();
    expect((bool) $unpaidEntries->firstWhere('family_code', 'SSP-CW2')['has_previous_year_payment'])->toBeTrue();
    expect($unpaidEntries->firstWhere('family_code', 'SSP-CW2')['previous_paid_year'])->toBe(now()->year - 1);
    expect($unpaidEntries->firstWhere('family_code', 'SSP-CW2')['previous_paid_year_short'])->toBe(substr((string) (now()->year - 1), -2));
    expect($unpaidEntries->firstWhere('family_code', 'SSP-CW2')['previous_year_badge'])->toBe(substr((string) (now()->year - 1), -2));
    expect($unpaidEntries->firstWhere('family_code', 'SSP-CW2')['previous_year_tooltip'])->toBe('Bayar tahun '.(now()->year - 1));
});

it('teacher can view other class details without admin whatsapp access', function () {
    [$teacher] = seedClassProgressWhatsappDataset();

    $detailsResponse = $this->actingAs($teacher)->getJson(route('teacher.class-progress.details', ['class' => '1 Alamanda']).'?billing_year='.now()->year);

    $detailsResponse->assertOk()
        ->assertJsonPath('summary.class_name', '1 Alamanda')
        ->assertJsonPath('summary.is_my_class', false)
        ->assertJsonPath('summary_only', false)
        ->assertJsonPath('can_view_full_details', true);

    $paidEntries = collect($detailsResponse->json('paid_entries'));
    $unpaidEntries = collect($detailsResponse->json('unpaid_entries'));

    expect($paidEntries->count())->toBe(1);
    expect($unpaidEntries->count())->toBe(0);
    expect($paidEntries->first()['student_name_display'])->toBe('CITRA HUSNA');

    $otherUnpaidResponse = $this->actingAs($teacher)->getJson(route('teacher.class-progress.details', ['class' => '1 Azalea']).'?billing_year='.now()->year);

    $otherUnpaidResponse->assertOk()
        ->assertJsonPath('summary.class_name', '1 Azalea')
        ->assertJsonPath('summary_only', false)
        ->assertJsonPath('can_view_full_details', true);

    $otherUnpaidEntries = collect($otherUnpaidResponse->json('unpaid_entries'));
    expect($otherUnpaidEntries->count())->toBe(1);
    expect($otherUnpaidEntries->first()['student_name_display'])->toBe('DANIA IMANI');
    expect($otherUnpaidEntries->first()['parent_name'])->toBe('PARENT DANIA IMANI');
    expect($otherUnpaidEntries->first()['parent_phone'])->toBeNull();

    $this->actingAs($teacher)
        ->get(route('admin.whatsapp-queue.index'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->assertForbidden();
});

it('queue dashboard shows scheduled messages', function () {
    [, , $admin] = seedClassProgressWhatsappDataset();

    WhatsAppMessageQueue::query()->create([
        'billing_year' => now()->year,
        'class_name' => '1 Angsana',
        'recipient_name' => 'Teacher Angsana',
        'recipient_phone' => '+60123456789',
        'message_type' => WhatsAppMessageQueue::MESSAGE_TYPE_CLASS_PAYMENT_REPORT,
        'message_part' => 'summary',
        'message_body' => 'Scheduled message',
        'status' => WhatsAppMessageQueue::STATUS_PENDING,
        'queued_by' => $admin->id,
        'queued_at' => now(),
        'scheduled_at' => now()->addMinutes(5),
        'total_parts' => 1,
        'part_order' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.whatsapp-queue.index', ['status' => 'scheduled']))
        ->assertOk()
        ->assertSee('Scheduled At')
        ->assertSee('Scheduled');
});

it('summary totals match expanded list totals for own class', function () {
    [$teacher] = seedClassProgressWhatsappDataset();

    $details = $this->actingAs($teacher)
        ->getJson(route('teacher.class-progress.details', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->assertOk()
        ->json();

    expect(count($details['paid_entries']))->toBe($details['summary']['fully_paid_families'] + $details['summary']['partial_paid_families']);
    expect(count($details['unpaid_entries']))->toBe($details['summary']['unpaid_families']);
});

it('unpaid family inherits most recent previous paid year from family or legacy history without affecting current totals', function () {
    [$teacher] = seedClassProgressWhatsappDataset();

    FamilyBilling::query()->create([
        'family_code' => 'SSP-CW2',
        'billing_year' => now()->year - 2,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    LegacyStudentPayment::query()->create([
        'student_no' => 'LEGACY-SSP-CW2',
        'family_code' => 'SSP-CW2',
        'student_name' => 'Badrul Hakim',
        'class_name' => '1 Angsana',
        'source_year' => now()->year - 1,
        'payment_status' => 'paid',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    $details = $this->actingAs($teacher)
        ->getJson(route('teacher.class-progress.details', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->assertOk()
        ->json();

    $entry = collect($details['unpaid_entries'])->firstWhere('family_code', 'SSP-CW2');

    expect($entry['previous_paid_year'])->toBe(now()->year - 1);
    expect($entry['previous_paid_year_short'])->toBe(substr((string) (now()->year - 1), -2));
    expect($entry['previous_year_badge'])->toBe(substr((string) (now()->year - 1), -2));
    expect($entry['previous_year_tooltip'])->toBe('Bayar tahun '.(now()->year - 1));
    expect($details['summary']['unpaid_families'])->toBe(1);
    expect((float) $details['summary']['completion_percent'])->toBe(50.0);
});

function seedClassProgressWhatsappDataset(
    bool $includeTeacherForAlamanda = true,
    bool $includePhoneForAlamandaTeacher = true,
    bool $includeTeacherForAzalea = true
): array {
    $year = (int) now()->year;

    $actor = User::factory()->create([
        'role' => 'teacher',
        'name' => 'ZZZ ACTOR TEACHER',
        'email' => 'actor.teacher@example.test',
        'class_name' => '1 Angsana',
        'phone' => '+60125550001',
        'email_verified_at' => now(),
    ]);

    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email' => 'admin.classprogress@example.test',
        'email_verified_at' => now(),
    ]);

    $teacherAngsana = User::factory()->create([
        'role' => 'teacher',
        'name' => 'Teacher Angsana',
        'email' => 'teacher.angsana@example.test',
        'class_name' => '1 Angsana',
        'phone' => '+60123456789',
        'email_verified_at' => now(),
    ]);

    if ($includeTeacherForAlamanda) {
        User::factory()->create([
            'role' => 'teacher',
            'name' => 'Teacher Alamanda',
            'email' => 'teacher.alamanda@example.test',
            'class_name' => '1 Alamanda',
            'phone' => $includePhoneForAlamandaTeacher ? '+60129876543' : null,
            'email_verified_at' => now(),
        ]);
    }

    if ($includeTeacherForAzalea) {
        User::factory()->create([
            'role' => 'teacher',
            'name' => 'Teacher Azalea',
            'email' => 'teacher.azalea@example.test',
            'class_name' => '1 Azalea',
            'phone' => '+60121111111',
            'email_verified_at' => now(),
        ]);
    }

    seedFamilyBillingAndStudent('SSP-CW1', '1 Angsana', 'Aina Sofea', $year, 100, 100, 'paid');
    seedFamilyBillingAndStudent('SSP-CW2', '1 Angsana', 'Badrul Hakim', $year, 100, 0, 'pending');
    seedFamilyBillingAndStudent('SSP-CW3', '1 Alamanda', 'Citra Husna', $year, 100, 100, 'paid');
    seedFamilyBillingAndStudent('SSP-CW4', '1 Azalea', 'Dania Imani', $year, 100, 0, 'pending');

    return [$actor, $teacherAngsana, $admin];
}

function seedFamilyBillingAndStudent(
    string $familyCode,
    string $className,
    string $studentName,
    int $billingYear,
    float $feeAmount,
    float $paidAmount,
    string $status
): void {
    FamilyBilling::query()->create([
        'family_code' => $familyCode,
        'billing_year' => $billingYear,
        'fee_amount' => $feeAmount,
        'paid_amount' => $paidAmount,
        'status' => $status,
    ]);

    Student::query()->create([
        'student_no' => 'STU-'.str_replace(' ', '-', $familyCode),
        'family_code' => $familyCode,
        'full_name' => $studentName,
        'class_name' => $className,
        'parent_name' => 'Parent '.$studentName,
        'parent_phone' => '0123456789',
        'parent_email' => strtolower(str_replace(' ', '.', $studentName)).'@example.test',
        'status' => 'active',
        'billing_year' => $billingYear,
        'annual_fee' => $feeAmount,
        'total_fee' => $feeAmount,
        'paid_amount' => $paidAmount,
    ]);
}
