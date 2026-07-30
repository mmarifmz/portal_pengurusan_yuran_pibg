<?php

use App\Models\FamilyBilling;
use App\Models\FamilyPaymentTransaction;
use App\Models\QrCampaign;
use App\Models\QrCampaignScan;
use App\Models\Student;
use App\Models\User;
use App\Services\ToyyibPayService;

function createQrAnalyticsCampaign(array $attributes = []): QrCampaign
{
    return QrCampaign::query()->create(array_merge([
        'name' => 'Bayaran PIBG 2026 - 6 Bestari',
        'purpose' => QrCampaign::PURPOSE_PAYMENT,
        'destination_type' => QrCampaign::DESTINATION_PAYMENT_DIRECTORY,
        'destination_path' => '/parent/search',
        'class_name' => '6 Bestari',
        'location_name' => 'Pagar Utama',
        'distribution_channel' => 'Poster Bercetak',
        'poster_title' => 'Jom Selesaikan Sumbangan PIBG',
        'poster_subtitle' => 'Mudah dan selamat melalui portal rasmi',
        'call_to_action' => 'Imbas untuk teruskan',
        'is_active' => true,
    ], $attributes));
}

function createQrAnalyticsParentFamily(): array
{
    $billing = FamilyBilling::query()->create([
        'family_code' => 'QR-ATTR-001',
        'billing_year' => now()->year,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Student::query()->create([
        'student_no' => 'QR-STUDENT-001',
        'family_code' => $billing->family_code,
        'full_name' => 'Murid QR',
        'class_name' => '6 Bestari',
        'parent_name' => 'Puan QR',
        'parent_phone' => '0123456789',
        'parent_email' => 'qr-parent@example.test',
        'billing_year' => now()->year,
    ]);

    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '0123456789',
        'email' => 'qr-parent@example.test',
        'name' => 'Puan QR',
    ]);

    return [$billing, $parent];
}

it('allows only system admins to manage QR campaigns', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);
    $teacher = User::factory()->create(['role' => 'teacher']);

    $this->actingAs($admin)
        ->get(route('system.qr-campaigns.index'))
        ->assertOk()
        ->assertSee('QR Kempen &amp; Analitik', false);

    $this->actingAs($teacher)
        ->get(route('system.qr-campaigns.index'))
        ->assertForbidden();
});

it('creates a class and channel attributable QR campaign with an internal destination', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);

    $response = $this->actingAs($admin)->post(route('system.qr-campaigns.store'), [
        'name' => 'Sumbangan 5 Cekal - WhatsApp',
        'purpose' => QrCampaign::PURPOSE_DONATION,
        'destination_type' => QrCampaign::DESTINATION_PAYMENT_DIRECTORY,
        'class_name' => '5 Cekal',
        'location_name' => 'Kantin',
        'distribution_channel' => 'WhatsApp Kelas',
        'poster_title' => 'Sumbangan Untuk Murid',
        'poster_subtitle' => 'Terima kasih atas sokongan anda',
        'call_to_action' => 'Imbas dan sumbang',
        'is_active' => '1',
    ]);

    $campaign = QrCampaign::query()->firstOrFail();

    $response->assertRedirect(route('system.qr-campaigns.index', ['edit' => $campaign->id]));
    expect($campaign->short_code)->toHaveLength(10);
    expect($campaign->destination_path)->toBe('/parent/search');
    expect($campaign->class_name)->toBe('5 Cekal');
    expect($campaign->distribution_channel)->toBe('WhatsApp Kelas');
});

it('rejects external and recursive destinations', function (string $path) {
    $admin = User::factory()->create(['role' => 'system_admin']);

    $this->actingAs($admin)
        ->post(route('system.qr-campaigns.store'), [
            'name' => 'Destinasi Tidak Selamat',
            'purpose' => QrCampaign::PURPOSE_EVENT,
            'destination_type' => QrCampaign::DESTINATION_CUSTOM_INTERNAL,
            'destination_path' => $path,
            'poster_title' => 'Acara',
            'call_to_action' => 'Imbas',
            'is_active' => '1',
        ])
        ->assertSessionHasErrors('destination_path');
})->with([
    'external URL' => ['https://example.com/payment'],
    'protocol-relative URL' => ['//example.com/payment'],
    'QR recursion' => ['/q/another-code'],
    'system space' => ['/system/payment-gateway-settings'],
]);

it('records a scan separately and redirects to the configured portal destination', function () {
    $campaign = createQrAnalyticsCampaign();

    $response = $this
        ->withHeader('User-Agent', 'QR-Test-Phone')
        ->get(route('qr-campaigns.redirect', ['qrCampaign' => $campaign->short_code]));

    $response->assertRedirect(url('/parent/search').'?qr_campaign='.$campaign->short_code);
    expect(QrCampaignScan::query()->count())->toBe(1);
    expect(FamilyPaymentTransaction::query()->count())->toBe(0);
    expect(session('qr_campaign_attribution.campaign_id'))->toBe($campaign->id);
    expect(QrCampaignScan::query()->firstOrFail()->visitor_hash)->toHaveLength(64);
});

it('does not record scans for inactive QR links', function () {
    $campaign = createQrAnalyticsCampaign(['is_active' => false]);

    $this->get(route('qr-campaigns.redirect', ['qrCampaign' => $campaign->short_code]))
        ->assertStatus(410);

    expect(QrCampaignScan::query()->count())->toBe(0);
});

it('associates a resulting payment attempt with the scanned QR campaign', function () {
    [$billing, $parent] = createQrAnalyticsParentFamily();
    $campaign = createQrAnalyticsCampaign();

    $toyyibPay = Mockery::mock(ToyyibPayService::class);
    $toyyibPay->shouldReceive('createBill')->once()->andReturn('BILL-QR-001');
    $toyyibPay->shouldReceive('paymentUrl')->once()->with('BILL-QR-001')->andReturn('https://toyyibpay.test/BILL-QR-001');
    $this->app->instance(ToyyibPayService::class, $toyyibPay);

    $this->actingAs($parent)
        ->withSession([
            'parent_child_selection_completed' => true,
            'parent_selected_family_billing_id' => $billing->id,
            'qr_campaign_attribution' => [
                'campaign_id' => $campaign->id,
                'scanned_at' => now()->toIso8601String(),
            ],
        ])
        ->post(route('parent.payments.create', $billing), [
            'payer_name' => 'Puan QR',
            'payer_email' => 'qr-parent@example.test',
            'payer_phone' => '0123456789',
            'donation_preset' => 0,
            'donation_custom' => 0,
            'donation_intention' => '',
        ])
        ->assertRedirect('https://toyyibpay.test/BILL-QR-001');

    expect(FamilyPaymentTransaction::query()->firstOrFail()->qr_campaign_id)->toBe($campaign->id);
});

it('downloads a real PNG QR and printable A4 PDF poster', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);
    $campaign = createQrAnalyticsCampaign();

    $png = $this->actingAs($admin)->get(route('system.qr-campaigns.qr-image', $campaign));
    $png->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($png->getContent())->toStartWith("\x89PNG");

    $pdf = $this->actingAs($admin)->get(route('system.qr-campaigns.poster', $campaign));
    $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF');
});
