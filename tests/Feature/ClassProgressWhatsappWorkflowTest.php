<?php

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppMessageQueue;
use App\Services\PaymentReportingService;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('class whatsapp preview returns 3 message parts', function () {
    [$teacher] = seedClassProgressWhatsappDataset();

    $response = $this->actingAs($teacher)->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year);

    $response
        ->assertOk()
        ->assertJsonPath('class_name', '1 Angsana')
        ->assertJsonPath('teacher_name', 'TEACHER ANGSANA');

    $parts = collect($response->json('generated_messages'))->pluck('message_part')->unique()->values()->all();
    expect($parts)->toBe(['summary', 'paid_list', 'unpaid_list']);
});

it('missing teacher disables queue', function () {
    [$teacher] = seedClassProgressWhatsappDataset(includeTeacherForAlamanda: false);

    $response = $this->actingAs($teacher)->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Alamanda']).'?billing_year='.now()->year);

    $response
        ->assertOk()
        ->assertJsonPath('queue_eligibility.ready', false)
        ->assertJsonPath('queue_eligibility.status', 'missing_teacher');
});

it('missing phone disables queue', function () {
    [$teacher] = seedClassProgressWhatsappDataset(includePhoneForAlamandaTeacher: false);

    $response = $this->actingAs($teacher)->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Alamanda']).'?billing_year='.now()->year);

    $response
        ->assertOk()
        ->assertJsonPath('queue_eligibility.ready', false)
        ->assertJsonPath('queue_eligibility.status', 'missing_phone');
});

it('queue endpoint creates pending records', function () {
    [$teacher] = seedClassProgressWhatsappDataset();

    $preview = $this->actingAs($teacher)
        ->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->assertOk()
        ->json();

    $queueResponse = $this->actingAs($teacher)->postJson(route('admin.classes.whatsapp-queue', ['class' => '1 Angsana']), [
        'billing_year' => now()->year,
        'preview_token' => $preview['preview_token'],
    ]);

    $queueResponse->assertOk();

    expect(WhatsAppMessageQueue::query()->where('class_name', '1 Angsana')->count())->toBe(3);
    expect(WhatsAppMessageQueue::query()->where('class_name', '1 Angsana')->where('status', 'pending')->count())->toBe(3);
});

it('duplicate recent queue warning works', function () {
    [$teacher] = seedClassProgressWhatsappDataset();

    $firstPreview = $this->actingAs($teacher)
        ->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->json();

    $this->actingAs($teacher)->postJson(route('admin.classes.whatsapp-queue', ['class' => '1 Angsana']), [
        'billing_year' => now()->year,
        'preview_token' => $firstPreview['preview_token'],
    ])->assertOk();

    $secondPreview = $this->actingAs($teacher)
        ->getJson(route('admin.classes.whatsapp-preview', ['class' => '1 Angsana']).'?billing_year='.now()->year)
        ->json();

    $duplicateResponse = $this->actingAs($teacher)->postJson(route('admin.classes.whatsapp-queue', ['class' => '1 Angsana']), [
        'billing_year' => now()->year,
        'preview_token' => $secondPreview['preview_token'],
    ]);

    $duplicateResponse
        ->assertStatus(409)
        ->assertJsonPath('requires_confirmation', true);
});

it('batch preview identifies eligible and skipped classes', function () {
    [$teacher] = seedClassProgressWhatsappDataset(includeTeacherForAzalea: false, includePhoneForAlamandaTeacher: false);

    $response = $this->actingAs($teacher)->getJson(route('admin.classes.whatsapp-batch-preview').'?billing_year='.now()->year);

    $response->assertOk();
    $cards = collect($response->json('class_previews'))->keyBy('class_name');

    expect($cards->get('1 Angsana')['status'])->toBe('ready');
    expect($cards->get('1 Alamanda')['status'])->toBe('missing_phone');
    expect($cards->get('1 Azalea')['status'])->toBe('missing_teacher');
});

it('batch queue creates queue records only for selected eligible classes', function () {
    [$teacher] = seedClassProgressWhatsappDataset(includeTeacherForAzalea: false, includePhoneForAlamandaTeacher: false);

    $preview = $this->actingAs($teacher)
        ->getJson(route('admin.classes.whatsapp-batch-preview').'?billing_year='.now()->year)
        ->assertOk()
        ->json();

    $queueResponse = $this->actingAs($teacher)->postJson(route('admin.classes.whatsapp-batch-queue'), [
        'billing_year' => now()->year,
        'preview_token' => $preview['preview_token'],
        'class_names' => ['1 Angsana'],
    ]);

    $queueResponse->assertOk();

    expect(WhatsAppMessageQueue::query()->where('class_name', '1 Angsana')->count())->toBe(3);
    expect(WhatsAppMessageQueue::query()->where('class_name', '1 Alamanda')->count())->toBe(0);
    expect(WhatsAppMessageQueue::query()->where('class_name', '1 Azalea')->count())->toBe(0);
});

it('message stats match leaderboard service', function () {
    [$teacher] = seedClassProgressWhatsappDataset();

    $preview = $this->actingAs($teacher)
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

function seedClassProgressWhatsappDataset(
    bool $includeTeacherForAlamanda = true,
    bool $includePhoneForAlamandaTeacher = true,
    bool $includeTeacherForAzalea = true
): array {
    $year = (int) now()->year;

    $actor = User::factory()->create([
        'role' => 'teacher',
        'email' => 'actor.teacher@example.test',
        'class_name' => null,
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

    return [$actor, $teacherAngsana];
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
