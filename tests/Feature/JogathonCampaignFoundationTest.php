<?php

use App\Models\JogathonAudit;
use App\Models\JogathonCampaign;
use App\Models\JogathonCause;
use App\Models\JogathonClassTeacher;
use App\Models\JogathonContribution;
use App\Models\JogathonParticipant;
use App\Models\Student;
use App\Models\User;
use App\Services\JogathonCampaignFoundationService;
use App\Services\JogathonParticipantProvisioningService;
use App\Support\JogathonAmount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function createJogathonStudent(string $number, string $name, string $className, string $status = Student::STATUS_ACTIVE): Student
{
    return Student::query()->create([
        'student_no' => $number,
        'full_name' => $name,
        'class_name' => $className,
        'status' => $status,
    ]);
}

test('only active students are provisioned and reruns do not duplicate participants', function () {
    $campaign = JogathonCampaign::factory()->create();
    createJogathonStudent('J001', 'Nur Aqilah', '1 AKASIA');
    createJogathonStudent('J002', 'Murid Berpindah', '1 AKASIA', Student::STATUS_TRANSFERRED);

    $service = app(JogathonParticipantProvisioningService::class);
    $first = $service->provision($campaign);
    $second = $service->provision($campaign);

    expect($first)->toMatchArray(['eligible' => 1, 'created' => 1, 'refreshed' => 0, 'withdrawn' => 0])
        ->and($second)->toMatchArray(['eligible' => 1, 'created' => 0, 'refreshed' => 1, 'withdrawn' => 0])
        ->and(JogathonParticipant::query()->count())->toBe(1);
});

test('duplicate names receive different non identifying stable public slugs and rename does not change an issued slug', function () {
    $campaign = JogathonCampaign::factory()->create();
    $firstStudent = createJogathonStudent('J010', 'Nur Aqilah', '2 AKASIA');
    createJogathonStudent('J011', 'Nur Aqilah', '2 ALAMANDA');

    $service = app(JogathonParticipantProvisioningService::class);
    $service->provision($campaign);

    $slugs = JogathonParticipant::query()->orderBy('student_id')->pluck('public_slug');
    $originalSlug = $slugs->first();
    $originalDisplayName = JogathonParticipant::query()->where('student_id', $firstStudent->id)->value('public_display_name');

    $firstStudent->update(['full_name' => 'Nur Aqilah Baharu', 'class_name' => '3 AKASIA']);
    $service->provision($campaign);

    $participant = JogathonParticipant::query()->where('student_id', $firstStudent->id)->firstOrFail();

    expect($slugs->unique()->count())->toBe(2)
        ->and($slugs->every(fn (string $slug): bool => str_starts_with($slug, 'pelari-')))->toBeTrue()
        ->and($slugs->contains(fn (string $slug): bool => str_contains($slug, 'nur-aqilah')))->toBeFalse()
        ->and($participant->public_slug)->toBe($originalSlug)
        ->and($participant->public_display_name)->toBe($originalDisplayName)
        ->and($participant->public_display_name)->not->toBe('Nur Aqilah')
        ->and($participant->class_name_snapshot)->toBe('3 AKASIA');
});

test('transferred participants are retained historically but become ineligible and private', function () {
    $campaign = JogathonCampaign::factory()->create();
    $student = createJogathonStudent('J020', 'Murid Aktif', '4 AKASIA');
    $service = app(JogathonParticipantProvisioningService::class);
    $service->provision($campaign);

    $participant = JogathonParticipant::query()->firstOrFail();
    $participant->update(['is_published' => true]);
    $student->update(['status' => Student::STATUS_TRANSFERRED]);

    $result = $service->provision($campaign);
    $participant->refresh();

    expect($result['withdrawn'])->toBe(1)
        ->and($participant->exists)->toBeTrue()
        ->and($participant->is_eligible)->toBeFalse()
        ->and($participant->is_published)->toBeFalse()
        ->and($participant->withdrawn_at)->not->toBeNull();
});

test('campaign creation installs five configurable causes with integer targets', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);
    $campaign = app(JogathonCampaignFoundationService::class)->create([
        'name' => 'Jogathon Digital 2026',
        'status' => JogathonCampaign::STATUS_DRAFT,
        'default_target_amount_sen' => 50_000,
        'default_target_distance_cm' => 500_000,
        'show_class_publicly' => false,
        'allow_public_indexing' => false,
        'allow_unspecified_cause' => false,
    ], $admin);

    expect($campaign->causes()->count())->toBe(5)
        ->and($campaign->causes()->sum('target_amount_sen'))->toBe(15_000_000)
        ->and($campaign->causes()->where('name', 'Program Transformasi Sukan Sekolah')->value('target_amount_sen'))->toBe(2_000_000);
});

test('only system admin may administer Jogathon campaigns', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);
    $teacher = User::factory()->create(['role' => 'teacher']);

    $this->actingAs($admin)->get(route('system.jogathon.campaigns.index'))->assertOk();
    $this->actingAs($teacher)->get(route('system.jogathon.campaigns.index'))->assertForbidden();
});

test('backend navigation and direct routes are restricted to jogathon mini app', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('system.jogathon.campaigns.index'));

    $this->actingAs($admin)
        ->get(route('system.jogathon.campaigns.index'))
        ->assertOk()
        ->assertSee('Laman Kempen')
        ->assertSee('Kad Jogathon')
        ->assertSee('Admin Jogathon')
        ->assertDontSee('School Calendar')
        ->assertDontSee('Class Progress')
        ->assertDontSee('Student Directory')
        ->assertDontSee('Finance Accounting')
        ->assertDontSee('Payment Funnel')
        ->assertDontSee('Parent Management')
        ->assertDontSee('API Access')
        ->assertDontSee('WhatsApp Queue')
        ->assertDontSee('Backup DB');

    foreach ([
        '/school-calendar',
        '/teacher/class-progress',
        '/teacher/records',
        '/teacher/api-access/docs',
        '/teacher/finance-accounting',
        '/teacher/parent-management',
        '/students/import',
        '/system/portal-seo',
        '/system/payment-gateway-settings',
        '/system/payment-funnel-monitor',
        '/system/visitor-logs',
        '/admin/whatsapp-queue/teacher-payment-notifications',
    ] as $legacyPath) {
        $this->actingAs($admin)->get($legacyPath)->assertNotFound();
    }
});

test('campaign target conversion stores exact integer sen and centimetres', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);

    $this->actingAs($admin)->post(route('system.jogathon.campaigns.store'), [
        'name' => 'Jogathon Tepat',
        'status' => JogathonCampaign::STATUS_DRAFT,
        'default_target_amount_rm' => '500.00',
    ])->assertRedirect();

    $campaign = JogathonCampaign::query()->where('name', 'Jogathon Tepat')->firstOrFail();

    expect((int) $campaign->default_target_amount_sen)->toBe(50_000)
        ->and((int) $campaign->default_target_distance_cm)->toBe(500_000);
});

test('system admin can activate campaign and publish participants with non identifying aliases and slugs', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);
    $campaign = JogathonCampaign::factory()->create([
        'status' => JogathonCampaign::STATUS_DRAFT,
        'allow_public_indexing' => true,
    ]);
    $firstStudent = createJogathonStudent('J025', 'Nur Aqilah Binti Rahman', '2 AKASIA');
    $secondStudent = createJogathonStudent('J026', 'Muhammad Adam Bin Ali', '2 AKASIA');
    $optedOutStudent = createJogathonStudent('J027', 'Murid Opt Out', '2 AKASIA');

    $firstParticipant = JogathonParticipant::factory()->create([
        'campaign_id' => $campaign->id,
        'student_id' => $firstStudent->id,
        'public_slug' => 'nur-aqilah-binti-rahman-a1b2c',
        'public_display_name' => 'Nur Aqilah Binti Rahman',
        'class_name_snapshot' => '2 AKASIA',
        'is_eligible' => true,
        'is_published' => false,
    ]);
    $secondParticipant = JogathonParticipant::factory()->create([
        'campaign_id' => $campaign->id,
        'student_id' => $secondStudent->id,
        'public_slug' => 'pelari-sedia-001',
        'public_display_name' => 'Pelari Sedia',
        'class_name_snapshot' => '2 AKASIA',
        'is_eligible' => true,
        'is_published' => false,
    ]);
    $optedOutParticipant = JogathonParticipant::factory()->create([
        'campaign_id' => $campaign->id,
        'student_id' => $optedOutStudent->id,
        'public_slug' => 'murid-opt-out-a1b2c',
        'public_display_name' => 'Murid Opt Out',
        'class_name_snapshot' => '2 AKASIA',
        'is_eligible' => true,
        'is_published' => false,
        'participation_opt_out' => true,
    ]);

    $this->actingAs($admin)->post(route('system.jogathon.campaigns.publish-participants', $campaign), [
        'activate_campaign' => '1',
    ])->assertRedirect(route('system.jogathon.campaigns.index', ['campaign' => $campaign->id]));

    $campaign->refresh();
    $firstParticipant->refresh();
    $secondParticipant->refresh();
    $optedOutParticipant->refresh();

    expect($campaign->status)->toBe(JogathonCampaign::STATUS_ACTIVE)
        ->and($campaign->allow_public_indexing)->toBeFalse()
        ->and($firstParticipant->is_published)->toBeTrue()
        ->and($firstParticipant->public_display_name)->toBe('Pelari 2 AKASIA 001')
        ->and($firstParticipant->public_slug)->toStartWith('pelari-')
        ->and($firstParticipant->public_slug)->not->toContain('nur-aqilah')
        ->and($secondParticipant->is_published)->toBeTrue()
        ->and($secondParticipant->public_display_name)->toBe('Pelari Sedia')
        ->and($secondParticipant->public_slug)->toBe('pelari-sedia-001')
        ->and($optedOutParticipant->is_published)->toBeFalse()
        ->and(JogathonAudit::query()->where('action', 'participants.safe_published')->count())->toBe(1);
});

test('safe publish can be scoped to one class', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);
    $campaign = JogathonCampaign::factory()->create(['status' => JogathonCampaign::STATUS_ACTIVE]);
    $akasiaStudent = createJogathonStudent('J028', 'Nama Akasia', '3 AKASIA');
    $bestariStudent = createJogathonStudent('J029', 'Nama Bestari', '4 BESTARI');
    $akasia = JogathonParticipant::factory()->create([
        'campaign_id' => $campaign->id,
        'student_id' => $akasiaStudent->id,
        'public_display_name' => 'Nama Akasia',
        'class_name_snapshot' => '3 AKASIA',
        'is_eligible' => true,
        'is_published' => false,
    ]);
    $bestari = JogathonParticipant::factory()->create([
        'campaign_id' => $campaign->id,
        'student_id' => $bestariStudent->id,
        'public_display_name' => 'Nama Bestari',
        'class_name_snapshot' => '4 BESTARI',
        'is_eligible' => true,
        'is_published' => false,
    ]);

    $this->actingAs($admin)->post(route('system.jogathon.campaigns.publish-participants', $campaign), [
        'class_name' => '3 AKASIA',
    ])->assertRedirect();

    expect($akasia->fresh()->is_published)->toBeTrue()
        ->and($akasia->fresh()->public_display_name)->toBe('Pelari 3 AKASIA 001')
        ->and($bestari->fresh()->is_published)->toBeFalse();
});

test('ringgit conversion never uses floating point storage arithmetic', function () {
    expect(JogathonAmount::senFromRinggit('0.01'))->toBe(1)
        ->and(JogathonAmount::senFromRinggit('20.5'))->toBe(2_050)
        ->and(JogathonAmount::senFromRinggit('500.00'))->toBe(50_000)
        ->and(JogathonAmount::distanceCmFromSen(2_000))->toBe(20_000);
});

test('system admin can finalise physical card collection with audit trail', function () {
    $admin = User::factory()->create(['role' => 'system_admin']);
    $campaign = JogathonCampaign::factory()->create(['status' => JogathonCampaign::STATUS_ACTIVE]);
    $cause = JogathonCause::factory()->create([
        'campaign_id' => $campaign->id,
        'name' => 'Program Transformasi Sukan Sekolah',
        'is_active' => true,
        'archived_at' => null,
    ]);
    $student = createJogathonStudent('J030', 'Nama Fizikal Peribadi', '5 BESTARI');
    $participant = JogathonParticipant::factory()->create([
        'campaign_id' => $campaign->id,
        'student_id' => $student->id,
        'public_display_name' => 'Pelari Fizikal',
        'class_name_snapshot' => '5 BESTARI',
        'is_eligible' => true,
        'is_published' => true,
        'participation_opt_out' => false,
        'withdrawn_at' => null,
    ]);

    $this->actingAs($admin)->post(route('system.jogathon.participants.physical-contributions.store', $participant), [
        'amount_rm' => '50.00',
        'cause_id' => $cause->id,
        'donor_display_name' => 'Nama Penyokong Kad',
        'collection_reference' => 'KAD-001',
        'received_on' => '2026-08-10',
        'note' => 'Serahan kutipan pertama',
    ])->assertRedirect();

    $contribution = JogathonContribution::query()->firstOrFail();

    expect($contribution->campaign_id)->toBe($campaign->id)
        ->and($contribution->participant_id)->toBe($participant->id)
        ->and($contribution->cause_id)->toBe($cause->id)
        ->and($contribution->source)->toBe(JogathonContribution::SOURCE_PHYSICAL_CARD)
        ->and($contribution->status)->toBe(JogathonContribution::STATUS_FINALISED)
        ->and($contribution->amount_sen)->toBe(5_000)
        ->and($contribution->distance_cm)->toBe(50_000)
        ->and($contribution->entered_by_user_id)->toBe($admin->id)
        ->and($contribution->metadata['collection_reference'])->toBe('KAD-001')
        ->and(JogathonAudit::query()->where('action', 'physical_contribution.finalised')->count())->toBe(1);
});

test('class teacher can enter only own class physical card collection', function () {
    $campaign = JogathonCampaign::factory()->create(['status' => JogathonCampaign::STATUS_ACTIVE]);
    $cause = JogathonCause::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
        'archived_at' => null,
    ]);
    $student = createJogathonStudent('J040', 'Nama Kelas Peribadi', '3 AKASIA');
    $participant = JogathonParticipant::factory()->create([
        'campaign_id' => $campaign->id,
        'student_id' => $student->id,
        'class_name_snapshot' => '3 AKASIA',
        'is_eligible' => true,
        'participation_opt_out' => false,
        'withdrawn_at' => null,
    ]);
    $classTeacher = User::factory()->create(['role' => 'teacher', 'class_name' => '3 AKASIA']);
    $otherTeacher = User::factory()->create(['role' => 'teacher', 'class_name' => '4 BESTARI']);

    $this->actingAs($otherTeacher)->post(route('system.jogathon.participants.physical-contributions.store', $participant), [
        'amount_rm' => '10.00',
        'cause_id' => $cause->id,
    ])->assertForbidden();

    $this->actingAs($classTeacher)->post(route('system.jogathon.participants.physical-contributions.store', $participant), [
        'amount_rm' => '10.00',
        'cause_id' => $cause->id,
    ])->assertRedirect();

    expect(JogathonContribution::query()->count())->toBe(1)
        ->and(JogathonContribution::query()->value('entered_by_user_id'))->toBe($classTeacher->id);
});

test('teacher can register only own class physical card number and duplicate numbers are rejected', function () {
    $campaign = JogathonCampaign::factory()->create(['status' => JogathonCampaign::STATUS_ACTIVE]);
    $ownStudent = createJogathonStudent('J041', 'Nama Kelas Sendiri', '3 AKASIA');
    $otherStudent = createJogathonStudent('J042', 'Nama Kelas Lain', '4 BESTARI');
    $ownParticipant = JogathonParticipant::factory()->create([
        'campaign_id' => $campaign->id,
        'student_id' => $ownStudent->id,
        'class_name_snapshot' => '3 AKASIA',
        'is_eligible' => true,
        'participation_opt_out' => false,
        'withdrawn_at' => null,
    ]);
    $otherParticipant = JogathonParticipant::factory()->create([
        'campaign_id' => $campaign->id,
        'student_id' => $otherStudent->id,
        'class_name_snapshot' => '4 BESTARI',
        'physical_card_number' => 'ssp-0008',
        'is_eligible' => true,
        'participation_opt_out' => false,
        'withdrawn_at' => null,
    ]);
    $classTeacher = User::factory()->create(['role' => 'teacher', 'class_name' => '3 AKASIA']);

    $this->actingAs($classTeacher)
        ->get(route('teacher.jogathon.cards.index'))
        ->assertOk()
        ->assertSee('NAMA KELAS SENDIRI')
        ->assertDontSee('NAMA KELAS LAIN');

    $this->actingAs($classTeacher)->patch(route('system.jogathon.participants.physical-card-number.update', $otherParticipant), [
        'physical_card_number' => 'ssp-0009',
    ])->assertForbidden();

    $this->actingAs($classTeacher)->patch(route('system.jogathon.participants.physical-card-number.update', $ownParticipant), [
        'physical_card_number' => 'SSP 0007',
    ])->assertRedirect();

    $this->actingAs($classTeacher)->patch(route('system.jogathon.participants.physical-card-number.update', $ownParticipant), [
        'physical_card_number' => 'ssp-0008',
    ])->assertSessionHasErrors('physical_card_number');

    expect($ownParticipant->fresh()->physical_card_number)->toBe('ssp-0007')
        ->and(JogathonAudit::query()->where('action', 'participant.physical_card_number_registered')->count())->toBe(1);
});

test('system admin imports minimal jogathon roster from external school api', function () {
    Http::fake([
        'https://source.example.test/api/v1/payment-status/search*' => Http::response([
            'success' => true,
            'count' => 1,
            'data' => [
                [
                    'family_code' => 'SSP-SECRET',
                    'guardian_name' => 'PARENT PRIVATE',
                    'students' => [
                        [
                            'name' => 'Murid Jogathon Satu',
                            'class' => '6 Alamanda',
                            'status' => 'Aktif',
                        ],
                        [
                            'name' => 'Murid Kelas Lain',
                            'class' => '5 Azalea',
                            'status' => 'Aktif',
                        ],
                    ],
                    'payment' => [
                        'receipt_url' => 'https://source.example.test/receipts/private',
                        'total_paid' => '100.00',
                    ],
                ],
            ],
        ]),
    ]);

    $admin = User::factory()->create(['role' => 'system_admin']);
    $campaign = JogathonCampaign::factory()->create(['status' => JogathonCampaign::STATUS_ACTIVE]);

    $this->actingAs($admin)->post(route('system.jogathon.campaigns.roster-import.store', $campaign), [
        'endpoint' => 'https://source.example.test/api/v1/payment-status/search',
        'api_key' => 'source_live_secret_key',
        'year' => 2026,
        'class_names' => '6 ALAMANDA',
        'keywords' => 'murid',
        'teacher_mappings' => '6 ALAMANDA=Cikgu Jogathon',
        'provision_participants' => '1',
    ])->assertRedirect(route('system.jogathon.campaigns.index', ['campaign' => $campaign->id]));

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer source_live_secret_key')
        && $request['q'] === 'murid'
        && $request['year'] === 2026
        && $request['class'] === '6 ALAMANDA');

    $student = Student::query()->where('full_name', 'MURID JOGATHON SATU')->firstOrFail();

    expect($student->student_no)->toStartWith('JOG-')
        ->and($student->class_name)->toBe('6 ALAMANDA')
        ->and($student->getRawOriginal('family_code'))->toBeNull()
        ->and($student->getRawOriginal('parent_name'))->toBeNull()
        ->and($student->getRawOriginal('parent_phone'))->toBeNull()
        ->and($student->getRawOriginal('parent_email'))->toBeNull()
        ->and(Student::query()->where('full_name', 'MURID KELAS LAIN')->exists())->toBeFalse()
        ->and(JogathonClassTeacher::query()->where('class_name', '6 ALAMANDA')->value('teacher_name'))->toBe('CIKGU JOGATHON')
        ->and(JogathonParticipant::query()->where('student_id', $student->id)->exists())->toBeTrue();
});

test('roster import updates matching existing student by name and class instead of duplicating', function () {
    Http::fake([
        'https://source.example.test/api/v1/payment-status/search*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'students' => [
                        [
                            'name' => 'Murid Sedia Ada',
                            'class' => '2 Akasia',
                            'status' => 'Aktif',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $admin = User::factory()->create(['role' => 'system_admin']);
    $campaign = JogathonCampaign::factory()->create(['status' => JogathonCampaign::STATUS_ACTIVE]);
    $existingStudent = createJogathonStudent('PIBG-EXISTING-001', 'MURID SEDIA ADA', '2 AKASIA');

    $this->actingAs($admin)->post(route('system.jogathon.campaigns.roster-import.store', $campaign), [
        'endpoint' => 'https://source.example.test/api/v1/payment-status/search',
        'api_key' => 'source_live_secret_key',
        'year' => 2026,
        'class_names' => '2 AKASIA',
        'keywords' => 'murid',
        'provision_participants' => '1',
    ])->assertRedirect();

    expect(Student::query()->where('full_name', 'MURID SEDIA ADA')->where('class_name', '2 AKASIA')->count())->toBe(1)
        ->and($existingStudent->fresh()->student_no)->toBe('PIBG-EXISTING-001')
        ->and(JogathonParticipant::query()->where('student_id', $existingStudent->id)->exists())->toBeTrue();
});

test('admin login import publish and teacher login card registration simulation', function () {
    Http::fake([
        'https://source.example.test/api/v1/payment-status/search*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'family_code' => 'SOURCE-FAMILY-SHOULD-NOT-BE-STORED',
                    'guardian_name' => 'SOURCE GUARDIAN SHOULD NOT BE STORED',
                    'students' => [
                        [
                            'name' => 'Murid Simulasi Admin Teacher',
                            'class' => '6 Alamanda',
                            'status' => 'Aktif',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email' => 'admin.simulation@example.test',
    ]);
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email' => 'teacher.6alamanda@example.test',
        'class_name' => '6 ALAMANDA',
    ]);
    $otherTeacher = User::factory()->create([
        'role' => 'teacher',
        'email' => 'teacher.other@example.test',
        'class_name' => '5 AZALEA',
    ]);
    $campaign = JogathonCampaign::factory()->create([
        'name' => 'Larian Sihat Jogathon 2026',
        'status' => JogathonCampaign::STATUS_ACTIVE,
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Jogathon Digital SK Sri Petaling')
        ->assertDontSee('Sumbangan PIBG');

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect();
    $this->assertAuthenticatedAs($admin);

    $this->get(route('system.jogathon.campaigns.index', ['campaign' => $campaign->id]))
        ->assertOk()
        ->assertSee('Import Roster Jogathon');

    $this->post(route('system.jogathon.campaigns.roster-import.store', $campaign), [
        'endpoint' => 'https://source.example.test/api/v1/payment-status/search',
        'api_key' => 'source_live_secret_key',
        'year' => 2026,
        'class_names' => '6 ALAMANDA',
        'keywords' => 'murid',
        'teacher_mappings' => '6 ALAMANDA=Cikgu Simulasi',
        'provision_participants' => '1',
    ])->assertRedirect(route('system.jogathon.campaigns.index', ['campaign' => $campaign->id]));

    $student = Student::query()->where('full_name', 'MURID SIMULASI ADMIN TEACHER')->firstOrFail();
    $participant = JogathonParticipant::query()->where('student_id', $student->id)->firstOrFail();

    expect($student->class_name)->toBe('6 ALAMANDA')
        ->and($student->getRawOriginal('family_code'))->toBeNull()
        ->and($student->getRawOriginal('parent_name'))->toBeNull()
        ->and(JogathonClassTeacher::query()->where('class_name', '6 ALAMANDA')->value('teacher_name'))->toBe('CIKGU SIMULASI')
        ->and($participant->physical_card_number)->toBeNull();

    $this->post(route('system.jogathon.campaigns.publish-participants', $campaign), [
        'class_name' => '6 ALAMANDA',
    ])->assertRedirect();

    $participant->refresh();
    expect($participant->is_published)->toBeTrue()
        ->and($participant->public_display_name)->toBe('Pelari 6 ALAMANDA 001');

    $this->post(route('logout'))->assertRedirect('/');

    $this->post(route('login.store'), [
        'email' => $teacher->email,
        'password' => 'password',
    ])->assertRedirect();
    $this->assertAuthenticatedAs($teacher);

    $this->get(route('teacher.jogathon.cards.index'))
        ->assertOk()
        ->assertSee('Daftar Nombor Kad Peserta')
        ->assertSee('MURID SIMULASI ADMIN TEACHER')
        ->assertSee('6 ALAMANDA');

    $this->patch(route('system.jogathon.participants.physical-card-number.update', $participant), [
        'physical_card_number' => 'SSP 0101',
    ])->assertRedirect();

    $participant->refresh();
    expect($participant->physical_card_number)->toBe('ssp-0101');

    $this->get(route('teacher.jogathon.cards.index'))
        ->assertOk()
        ->assertSee('/ssp-0101');

    $this->post(route('logout'))->assertRedirect('/');

    $this->post(route('login.store'), [
        'email' => $otherTeacher->email,
        'password' => 'password',
    ])->assertRedirect();
    $this->assertAuthenticatedAs($otherTeacher);

    $this->get(route('teacher.jogathon.cards.index'))
        ->assertOk()
        ->assertDontSee('MURID SIMULASI ADMIN TEACHER');

    $this->patch(route('system.jogathon.participants.physical-card-number.update', $participant), [
        'physical_card_number' => 'ssp-0102',
    ])->assertForbidden();

    $this->get('/ssp-0101')->assertOk();
});

test('physical card collection cannot be entered for inactive participant states', function (array $participantState) {
    $admin = User::factory()->create(['role' => 'system_admin']);
    $campaign = JogathonCampaign::factory()->create(['status' => JogathonCampaign::STATUS_ACTIVE]);
    $cause = JogathonCause::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
        'archived_at' => null,
    ]);
    $student = createJogathonStudent('J050', 'Nama Tidak Aktif', '2 AKASIA');
    $participant = JogathonParticipant::factory()->create(array_merge([
        'campaign_id' => $campaign->id,
        'student_id' => $student->id,
        'class_name_snapshot' => '2 AKASIA',
        'is_eligible' => true,
        'participation_opt_out' => false,
        'withdrawn_at' => null,
    ], $participantState));

    $this->actingAs($admin)->post(route('system.jogathon.participants.physical-contributions.store', $participant), [
        'amount_rm' => '10.00',
        'cause_id' => $cause->id,
    ])->assertStatus(422);

    expect(JogathonContribution::query()->count())->toBe(0);
})->with([
    'ineligible' => [['is_eligible' => false]],
    'opted out' => [['participation_opt_out' => true]],
    'withdrawn' => [['withdrawn_at' => now()]],
]);
