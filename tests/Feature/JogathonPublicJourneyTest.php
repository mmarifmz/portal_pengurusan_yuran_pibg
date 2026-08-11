<?php

use App\Models\FamilyPaymentTransaction;
use App\Models\JogathonCampaign;
use App\Models\JogathonCause;
use App\Models\JogathonContribution;
use App\Models\JogathonParticipant;
use App\Models\JogathonParticipantVisit;
use App\Models\Student;
use App\Services\JogathonToyyibPayService;
use App\Support\JogathonAmount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publicJogathonParticipant(array $participantOverrides = [], array $campaignOverrides = []): JogathonParticipant
{
    static $sequence = 0;
    $sequence++;

    $campaign = JogathonCampaign::factory()->create(array_merge([
        'status' => JogathonCampaign::STATUS_ACTIVE,
        'show_class_publicly' => false,
        'allow_public_indexing' => false,
    ], $campaignOverrides));

    $student = Student::query()->create([
        'student_no' => 'PRIVATE-STUDENT-991-'.$sequence,
        'full_name' => 'Nama Murid Peribadi',
        'class_name' => '3 AKASIA',
        'family_code' => 'PRIVATE-FAMILY-771-'.$sequence,
        'parent_email' => 'private-parent-'.$sequence.'@example.test',
        'parent_phone' => '60123456789'.$sequence,
        'status' => Student::STATUS_ACTIVE,
    ]);

    return JogathonParticipant::factory()->create(array_merge([
        'campaign_id' => $campaign->id,
        'student_id' => $student->id,
        'public_slug' => 'pelari-harapan-7k3p-'.$sequence,
        'public_display_name' => 'Pelari Harapan',
        'class_name_snapshot' => '3 AKASIA',
        'target_amount_sen' => 50_000,
        'target_distance_cm' => 500_000,
        'is_eligible' => true,
        'is_published' => true,
        'participation_opt_out' => false,
        'withdrawn_at' => null,
    ], $participantOverrides));
}

function confirmedJogathonContribution(
    JogathonParticipant $participant,
    JogathonCause $cause,
    int $amountSen,
    string $source = JogathonContribution::SOURCE_ONLINE,
    ?string $status = null,
    array $overrides = [],
): JogathonContribution {
    return JogathonContribution::query()->create(array_merge([
        'campaign_id' => $participant->campaign_id,
        'participant_id' => $participant->id,
        'cause_id' => $cause->id,
        'source' => $source,
        'amount_sen' => $amountSen,
        'distance_cm' => JogathonAmount::distanceCmFromSen($amountSen),
        'status' => $status ?? ($source === JogathonContribution::SOURCE_ONLINE
            ? JogathonContribution::STATUS_SUCCESSFUL
            : JogathonContribution::STATUS_FINALISED),
        'donor_display_name' => 'Penyokong Baik',
        'received_at' => now(),
        'finalised_at' => now(),
    ], $overrides));
}

test('private disabled and opted out participant journeys return not found', function () {
    $draftParticipant = publicJogathonParticipant(campaignOverrides: [
        'status' => JogathonCampaign::STATUS_DRAFT,
    ]);

    $this->get(route('jogathon.public.participants.show', [
        $draftParticipant->campaign,
        $draftParticipant->public_slug,
    ]))->assertNotFound();

    $privateParticipant = publicJogathonParticipant(['is_published' => false]);
    $this->get(route('jogathon.public.participants.show', [
        $privateParticipant->campaign,
        $privateParticipant->public_slug,
    ]))->assertNotFound();

    $optedOutParticipant = publicJogathonParticipant(['participation_opt_out' => true]);
    $this->get(route('jogathon.public.participants.show', [
        $optedOutParticipant->campaign,
        $optedOutParticipant->public_slug,
    ]))->assertNotFound();
});

test('public participant html exposes approved fields but no private student or donor contact fields', function () {
    $participant = publicJogathonParticipant();
    $cause = JogathonCause::factory()->create(['campaign_id' => $participant->campaign_id]);
    confirmedJogathonContribution($participant, $cause, 2_000, overrides: [
        'donor_display_name' => 'Nama Penyumbang Awam',
        'is_anonymous_public' => true,
        'metadata' => [
            'email' => 'gateway-private@example.test',
            'phone' => '60999999999',
        ],
    ]);

    $response = $this->get(route('jogathon.public.participants.show', [
        $participant->campaign,
        $participant->public_slug,
    ]));

    $response->assertOk()
        ->assertSee('Pelari Harapan')
        ->assertSee('Tanpa Nama')
        ->assertDontSee('Nama Murid Peribadi')
        ->assertDontSee('PRIVATE-STUDENT-991')
        ->assertDontSee('PRIVATE-FAMILY-771')
        ->assertDontSee('private-parent-')
        ->assertDontSee('60123456789')
        ->assertDontSee('gateway-private@example.test')
        ->assertDontSee('60999999999')
        ->assertDontSee('Nama Penyumbang Awam');
});

test('physical card number opens participant page and records qr scan analytics', function () {
    $participant = publicJogathonParticipant([
        'physical_card_number' => 'ssp-0001',
    ]);

    $response = $this->get(route('jogathon.public.card.show', 'ssp-0001').'?src=qr');

    $response->assertOk()
        ->assertSee('Pelari Harapan')
        ->assertDontSee('Nama Murid Peribadi')
        ->assertDontSee('PRIVATE-STUDENT-991');

    $visit = JogathonParticipantVisit::query()->firstOrFail();

    expect($visit->campaign_id)->toBe($participant->campaign_id)
        ->and($visit->participant_id)->toBe($participant->id)
        ->and($visit->source)->toBe('qr')
        ->and($visit->channel)->toBe('qr')
        ->and($visit->access_point)->toBe('physical_card_short_url')
        ->and($visit->ip_hash)->not->toBeNull();
});

test('participant campaign url accepts card number and records social or copied link analytics', function () {
    $participant = publicJogathonParticipant([
        'physical_card_number' => 'ssp-0002',
    ]);

    $this->get(route('jogathon.public.participants.show', [
        $participant->campaign,
        'ssp-0002',
    ]).'?src=whatsapp')->assertOk();

    $this->get(route('jogathon.public.participants.show', [
        $participant->campaign,
        'ssp-0002',
    ]).'?src=copy')->assertOk();

    expect(JogathonParticipantVisit::query()->where('source', 'social')->where('channel', 'whatsapp')->count())->toBe(1)
        ->and(JogathonParticipantVisit::query()->where('source', 'direct_link')->where('channel', 'copy')->count())->toBe(1);
});

test('progress counts only successful online and finalised physical contributions', function () {
    $participant = publicJogathonParticipant();
    $cause = JogathonCause::factory()->create(['campaign_id' => $participant->campaign_id]);

    confirmedJogathonContribution($participant, $cause, 2_000);
    confirmedJogathonContribution($participant, $cause, 1_000, JogathonContribution::SOURCE_PHYSICAL_CARD);
    confirmedJogathonContribution($participant, $cause, 99_900, status: JogathonContribution::STATUS_PENDING);
    confirmedJogathonContribution($participant, $cause, 88_800, status: JogathonContribution::STATUS_FAILED);

    $this->get(route('jogathon.public.participants.show', [$participant->campaign, $participant->public_slug]))
        ->assertOk()
        ->assertSee('RM30.00')
        ->assertSee('300 m')
        ->assertSee('RM20.00')
        ->assertSee('RM10.00')
        ->assertDontSee('RM999.00')
        ->assertDontSee('RM888.00');
});

test('contribution distance is always derived from integer sen', function () {
    $participant = publicJogathonParticipant();
    $cause = JogathonCause::factory()->create(['campaign_id' => $participant->campaign_id]);

    $contribution = confirmedJogathonContribution($participant, $cause, 2_000, overrides: [
        'distance_cm' => 999_999,
    ]);

    expect($contribution->distance_cm)->toBe(20_000);
});

test('default target displays five kilometres and over target progress is not financially capped', function () {
    $participant = publicJogathonParticipant();
    $cause = JogathonCause::factory()->create(['campaign_id' => $participant->campaign_id]);
    confirmedJogathonContribution($participant, $cause, 60_000);

    $this->get(route('jogathon.public.participants.show', [$participant->campaign, $participant->public_slug]))
        ->assertOk()
        ->assertSee('6.00 km')
        ->assertSee('5,000 m')
        ->assertSee('RM600.00')
        ->assertSee('120.0%')
        ->assertSee('terus dikira melebihi sasaran');
});

test('class and indexing are displayed only when campaign privacy settings permit them', function () {
    $privateClassParticipant = publicJogathonParticipant();

    $this->get(route('jogathon.public.participants.show', [
        $privateClassParticipant->campaign,
        $privateClassParticipant->public_slug,
    ]))
        ->assertOk()
        ->assertSee('noindex, nofollow, noarchive', false)
        ->assertDontSee('Kelas 3 AKASIA');

    $publicClassParticipant = publicJogathonParticipant(campaignOverrides: [
        'show_class_publicly' => true,
        'allow_public_indexing' => true,
    ]);

    $this->get(route('jogathon.public.participants.show', [
        $publicClassParticipant->campaign,
        $publicClassParticipant->public_slug,
    ]))
        ->assertOk()
        ->assertSee('index, follow', false)
        ->assertSee('Kelas 3 AKASIA');
});

test('campaign landing exposes only published participant aliases and aggregate cause progress', function () {
    $participant = publicJogathonParticipant();
    $cause = JogathonCause::factory()->create([
        'campaign_id' => $participant->campaign_id,
        'name' => 'Naik Taraf Perpustakaan',
        'target_amount_sen' => 100_000,
    ]);
    confirmedJogathonContribution($participant, $cause, 25_000);

    JogathonParticipant::factory()->create([
        'campaign_id' => $participant->campaign_id,
        'public_display_name' => 'Alias Peribadi',
        'is_published' => false,
    ]);

    $this->get(route('jogathon.public.campaigns.show', $participant->campaign))
        ->assertOk()
        ->assertSee('Pelari Harapan')
        ->assertSee('Naik Taraf Perpustakaan')
        ->assertSee('RM250.00')
        ->assertSee('2,500 m')
        ->assertSee('25.0%')
        ->assertDontSee('Alias Peribadi')
        ->assertDontSee('PRIVATE-STUDENT-991');
});

test('campaign landing shows rm150k bucket plan leaderboard motivation prize and class alias directory', function () {
    $participant = publicJogathonParticipant(campaignOverrides: [
        'show_class_publicly' => true,
    ]);
    $campaign = $participant->campaign;

    $participant->update([
        'public_display_name' => 'Pelari Zamrud',
        'class_name_snapshot' => '3 AKASIA',
    ]);

    $secondStudent = Student::query()->create([
        'student_no' => 'PRIVATE-STUDENT-992',
        'full_name' => 'Nama Murid Kedua Peribadi',
        'class_name' => '4 BESTARI',
        'family_code' => 'PRIVATE-FAMILY-772',
        'parent_email' => 'private-second@example.test',
        'parent_phone' => '60122222222',
        'status' => Student::STATUS_ACTIVE,
    ]);

    $secondParticipant = JogathonParticipant::factory()->create([
        'campaign_id' => $campaign->id,
        'student_id' => $secondStudent->id,
        'public_slug' => 'pelari-biru',
        'public_display_name' => 'Pelari Biru',
        'class_name_snapshot' => '4 BESTARI',
        'target_amount_sen' => 50_000,
        'target_distance_cm' => 500_000,
        'is_eligible' => true,
        'is_published' => true,
        'participation_opt_out' => false,
        'withdrawn_at' => null,
    ]);

    $causes = collect(config('jogathon.initial_causes'))->map(fn (array $cause, int $index) => JogathonCause::factory()->create([
        'campaign_id' => $campaign->id,
        'name' => $cause['name'],
        'target_amount_sen' => $cause['target_amount_sen'],
        'sort_order' => $index + 1,
    ]));

    confirmedJogathonContribution($participant, $causes->first(), 20_000);
    confirmedJogathonContribution($secondParticipant, $causes->first(), 10_000);

    $response = $this->get(route('jogathon.public.campaigns.show', $campaign));

    $response->assertOk()
        ->assertSee('Sasaran sekolah: RM150,000.00')
        ->assertSee('Bagaimana sekolah merancang penggunaan RM150,000.00')
        ->assertSee('Bucket kempen')
        ->assertSee('Hadiah Motivasi Khas')
        ->assertSee('Top Achiever Jogathon')
        ->assertSee('Pendahulu semasa')
        ->assertSeeInOrder(['Pelari Zamrud', 'RM200.00', 'Pelari Biru', 'RM100.00'])
        ->assertSee('Kelas 3 AKASIA')
        ->assertSee('Kelas 4 BESTARI')
        ->assertSee('Nama penuh murid tidak dipaparkan')
        ->assertSee('Contoh: ssp-0001')
        ->assertSee('name="physical_card_number"', false)
        ->assertDontSee('w-[200%]', false)
        ->assertDontSee('overflow-x-auto')
        ->assertDontSee('Cari nama murid')
        ->assertDontSee('Nama Murid Kedua Peribadi')
        ->assertDontSee('PRIVATE-STUDENT-992')
        ->assertDontSee('PRIVATE-FAMILY-772')
        ->assertDontSee('private-second@example.test')
        ->assertDontSee('60122222222');
});

test('home page is the digital jogathon landing instead of the legacy pibg payment search page', function () {
    $participant = publicJogathonParticipant(campaignOverrides: [
        'show_class_publicly' => true,
    ]);

    collect(config('jogathon.initial_causes'))->each(fn (array $cause, int $index) => JogathonCause::factory()->create([
        'campaign_id' => $participant->campaign_id,
        'name' => $cause['name'],
        'target_amount_sen' => $cause['target_amount_sen'],
        'sort_order' => $index + 1,
    ]));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Kad kutipan digital')
        ->assertSee('Larian Sihat Jogathon 2026')
        ->assertSee('Bersama Melangkah, Bersama Membina')
        ->assertSee('Sasaran sekolah: RM150,000.00')
        ->assertSee('24 Okt 2026')
        ->assertSee('Kutipan: 5 Ogos - 24 Oktober 2026')
        ->assertSee('Minima: RM50 seorang')
        ->assertSee('Contoh: ssp-0001')
        ->assertDontSee('Semakan &amp; Bayaran', false)
        ->assertDontSee('Semak Nama Murid')
        ->assertDontSee('Cari nama murid');
});

test('participant qr is generated only for a public participant', function () {
    $participant = publicJogathonParticipant();

    $this->get(route('jogathon.public.participants.qr', [
        $participant->campaign,
        $participant->public_slug,
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png')
        ->assertHeader('x-robots-tag', 'noindex, nofollow');
});

test('individual participant page uses full student name in support section without exposing identifiers', function () {
    $participant = publicJogathonParticipant(campaignOverrides: [
        'show_class_publicly' => true,
    ]);
    JogathonCause::factory()->create([
        'campaign_id' => $participant->campaign_id,
        'name' => 'Menaik taraf Makmal ICT',
        'target_amount_sen' => 20_000_00,
    ]);

    $this->get(route('jogathon.public.participants.show', [
        $participant->campaign,
        $participant->public_slug,
    ]))
        ->assertOk()
        ->assertSee(route('jogathon.public.participants.donations.create', [
            $participant->campaign,
            $participant->public_slug,
        ]), false)
        ->assertSee('Sokong perjalanan NAMA MURID PERIBADI')
        ->assertSee('Sumbang untuk peserta ini')
        ->assertDontSee('PRIVATE-STUDENT-991')
        ->assertDontSee('PRIVATE-FAMILY-771')
        ->assertDontSee('private-parent-')
        ->assertDontSee('60123456789');

    $this->get(route('jogathon.public.participants.donations.create', [
        $participant->campaign,
        $participant->public_slug,
        'amount' => '30.00',
    ]))
        ->assertOk()
        ->assertSee('Halaman sumbangan peserta')
        ->assertSee('Pelari Harapan')
        ->assertSee('Kelas 3 AKASIA')
        ->assertSee('Sumbang untuk peserta ini')
        ->assertSee('Teruskan ke ToyyibPay')
        ->assertSee('value="30.00"', false)
        ->assertSee('Menaik taraf Makmal ICT')
        ->assertDontSee('Nama Murid Peribadi')
        ->assertDontSee('PRIVATE-STUDENT-991')
        ->assertDontSee('PRIVATE-FAMILY-771')
        ->assertDontSee('private-parent-')
        ->assertDontSee('60123456789');
});

test('campaign search opens participant by physical card number only', function () {
    $participant = publicJogathonParticipant([
        'physical_card_number' => 'ssp-0789',
    ], campaignOverrides: [
        'show_class_publicly' => true,
    ]);
    JogathonCause::factory()->create(['campaign_id' => $participant->campaign_id]);

    $this->post(route('jogathon.public.participants.search', $participant->campaign), [
        'physical_card_number' => 'SSP 0789',
    ])->assertRedirect(route('jogathon.public.participants.donations.create', [
        $participant->campaign,
        'ssp-0789',
    ]));

    $this->followingRedirects()
        ->post(route('jogathon.public.participants.search', $participant->campaign), [
            'physical_card_number' => 'ssp-0789',
        ])
        ->assertOk()
        ->assertSee('Halaman sumbangan peserta')
        ->assertSee('Pelari Harapan')
        ->assertDontSee('Nama Murid Peribadi')
        ->assertDontSee('PRIVATE-STUDENT-991')
        ->assertDontSee('PRIVATE-FAMILY-771');

    $this->post(route('jogathon.public.participants.search', $participant->campaign), [
        'student_name' => 'Nama Murid Peribadi',
    ])->assertSessionHasErrors('physical_card_number');
});

test('public donor can create a separate jogathon toyyibpay bill without touching pibg payment tables', function () {
    $participant = publicJogathonParticipant();
    $cause = JogathonCause::factory()->create([
        'campaign_id' => $participant->campaign_id,
        'name' => 'Program Transformasi Sukan Sekolah',
    ]);

    $toyyib = Mockery::mock(JogathonToyyibPayService::class);
    $toyyib->shouldReceive('createBill')
        ->once()
        ->withArgs(fn (array $payload): bool => ($payload['billAmount'] ?? null) === 2_000
            && ($payload['billCallbackUrl'] ?? null) === route('jogathon.donations.callback')
            && ($payload['billReturnUrl'] ?? null) === route('jogathon.donations.return')
            && str_contains((string) ($payload['billDescription'] ?? ''), 'Program Transformasi Sukan Sekolah'))
        ->andReturn('JOGBILL20');
    $toyyib->shouldReceive('paymentUrl')
        ->once()
        ->with('JOGBILL20')
        ->andReturn('https://toyyibpay.test/JOGBILL20');
    $this->app->instance(JogathonToyyibPayService::class, $toyyib);

    $this->post(route('jogathon.public.participants.donations.store', [
        $participant->campaign,
        $participant->public_slug,
    ]), [
        'amount' => '20.00',
        'cause_id' => $cause->id,
        'donor_name' => 'Puan Derma',
        'donor_email' => 'derma@example.test',
        'donor_phone' => '60123450000',
        'encouragement_message' => 'Terus maju',
    ])->assertRedirect('https://toyyibpay.test/JOGBILL20');

    $contribution = JogathonContribution::query()->firstOrFail();

    expect($contribution->source)->toBe(JogathonContribution::SOURCE_ONLINE)
        ->and($contribution->status)->toBe(JogathonContribution::STATUS_PENDING)
        ->and($contribution->amount_sen)->toBe(2_000)
        ->and($contribution->distance_cm)->toBe(20_000)
        ->and($contribution->cause_id)->toBe($cause->id)
        ->and($contribution->provider_bill_code)->toBe('JOGBILL20')
        ->and($contribution->metadata['donor_email'])->toBe('derma@example.test')
        ->and(FamilyPaymentTransaction::query()->count())->toBe(0);
});

test('jogathon browser return records metadata but does not mark contribution successful', function () {
    $participant = publicJogathonParticipant();
    $cause = JogathonCause::factory()->create(['campaign_id' => $participant->campaign_id]);
    $contribution = confirmedJogathonContribution($participant, $cause, 2_000, status: JogathonContribution::STATUS_PENDING, overrides: [
        'external_order_id' => 'JOG-RETURN-001',
        'provider_bill_code' => 'JOGRETURN001',
    ]);

    $this->get(route('jogathon.donations.return', [
        'status_id' => '1',
        'order_id' => 'JOG-RETURN-001',
        'billcode' => 'JOGRETURN001',
    ]))->assertRedirect(route('jogathon.donations.summary', 'JOG-RETURN-001'));

    $contribution->refresh();

    expect($contribution->status)->toBe(JogathonContribution::STATUS_PENDING)
        ->and($contribution->metadata['toyyibpay_return']['status_id'])->toBe('1');
});

test('jogathon signed callback finalises successful contribution exactly once', function () {
    $participant = publicJogathonParticipant();
    $cause = JogathonCause::factory()->create(['campaign_id' => $participant->campaign_id]);
    $contribution = confirmedJogathonContribution($participant, $cause, 2_000, status: JogathonContribution::STATUS_PENDING, overrides: [
        'external_order_id' => 'JOG-CALLBACK-001',
        'provider_bill_code' => 'JOGCALLBACK001',
    ]);

    $toyyib = Mockery::mock(JogathonToyyibPayService::class);
    $toyyib->shouldReceive('verifyCallbackHash')->twice()->andReturnTrue();
    $toyyib->shouldReceive('getBillTransactions')
        ->once()
        ->with('JOGCALLBACK001')
        ->andReturn([[
            'billExternalReferenceNo' => 'JOG-CALLBACK-001',
            'billpaymentAmount' => '20.00',
            'billpaymentInvoiceNo' => 'JOG-INV-001',
            'billPaymentDate' => '2026-08-10 10:30:00',
        ]]);
    $this->app->instance(JogathonToyyibPayService::class, $toyyib);

    $payload = [
        'status' => '1',
        'order_id' => 'JOG-CALLBACK-001',
        'refno' => 'JOG-REF-001',
        'hash' => 'valid-hash',
        'billcode' => 'JOGCALLBACK001',
    ];

    $this->post(route('jogathon.donations.callback'), $payload)->assertOk();
    $this->post(route('jogathon.donations.callback'), $payload)->assertOk();

    $contribution->refresh();

    expect($contribution->status)->toBe(JogathonContribution::STATUS_SUCCESSFUL)
        ->and($contribution->amount_sen)->toBe(2_000)
        ->and($contribution->distance_cm)->toBe(20_000)
        ->and($contribution->provider_reference)->toBe('JOG-INV-001')
        ->and($contribution->finalised_at)->not->toBeNull();

    $this->get(route('jogathon.public.participants.show', [$participant->campaign, $participant->public_slug]))
        ->assertOk()
        ->assertSee('RM20.00')
        ->assertSee('200 m');
});

test('jogathon callback rejects invalid hash and reconciliation amount mismatch', function () {
    $participant = publicJogathonParticipant();
    $cause = JogathonCause::factory()->create(['campaign_id' => $participant->campaign_id]);
    $contribution = confirmedJogathonContribution($participant, $cause, 2_000, status: JogathonContribution::STATUS_PENDING, overrides: [
        'external_order_id' => 'JOG-CALLBACK-BAD',
        'provider_bill_code' => 'JOGCALLBACKBAD',
    ]);

    $toyyib = Mockery::mock(JogathonToyyibPayService::class);
    $toyyib->shouldReceive('verifyCallbackHash')->twice()->andReturn(false, true);
    $toyyib->shouldReceive('getBillTransactions')
        ->once()
        ->andReturn([[
            'billExternalReferenceNo' => 'JOG-CALLBACK-BAD',
            'billpaymentAmount' => '19.00',
            'billpaymentInvoiceNo' => 'JOG-INV-BAD',
        ]]);
    $this->app->instance(JogathonToyyibPayService::class, $toyyib);

    $this->post(route('jogathon.donations.callback'), [
        'status' => '1',
        'order_id' => 'JOG-CALLBACK-BAD',
        'refno' => 'JOG-REF-BAD',
        'hash' => 'bad-hash',
        'billcode' => 'JOGCALLBACKBAD',
    ])->assertStatus(422);

    expect($contribution->fresh()->status)->toBe(JogathonContribution::STATUS_PENDING);

    $this->post(route('jogathon.donations.callback'), [
        'status' => '1',
        'order_id' => 'JOG-CALLBACK-BAD',
        'refno' => 'JOG-REF-BAD',
        'hash' => 'valid-hash',
        'billcode' => 'JOGCALLBACKBAD',
    ])->assertStatus(409);

    expect($contribution->fresh()->status)->toBe(JogathonContribution::STATUS_PENDING);
});
