<?php

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;
use Shetabit\Visitor\Middlewares\LogVisits;

beforeEach(function () {
    $this->withoutMiddleware(LogVisits::class);
});

it('allows teacher roles to access class progress page', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Angsana',
        'email_verified_at' => now(),
    ]);

    User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Angsana',
        'phone' => '0123001000',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    FamilyBilling::query()->create([
        'family_code' => 'SSP-CPG1',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'CPG-0001',
        'family_code' => 'SSP-CPG1',
        'full_name' => 'Aina Sofea',
        'class_name' => '1 Angsana',
        'parent_name' => 'Puan Niza',
        'parent_phone' => '0123456789',
        'status' => 'active',
        'billing_year' => $billingYear,
    ]);

    User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Alamanda',
        'phone' => '0123001001',
        'email_verified_at' => now(),
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'SSP-CPG3',
        'billing_year' => $billingYear,
        'fee_amount' => 100,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    Student::query()->create([
        'student_no' => 'CPG-0003',
        'family_code' => 'SSP-CPG3',
        'full_name' => 'Danish Irfan',
        'class_name' => '1 Alamanda',
        'parent_name' => 'Encik Firdaus',
        'parent_phone' => '0191234567',
        'status' => 'active',
        'billing_year' => $billingYear,
    ]);

    $response = $this->actingAs($teacher)->get(route('teacher.class-progress'));

    $response->assertOk();
    $response->assertSee('Pemantauan Kutipan Sumbangan PIBG Mengikut Kelas');
    $response->assertSee('Tapis Tahun');
    $response->assertSee('Kelas Saya');
    $response->assertSee('Senarai Kelas Lain');
    $response->assertSeeInOrder(['Kelas Saya', '1 Angsana', 'Senarai Kelas Lain', '1 Alamanda']);
    $response->assertSee('Laporan PDF Kelas');
    $response->assertSee(route('teacher.class-progress.pdf', [
        'class' => '1 Angsana',
        'billing_year' => $billingYear,
    ]), false);
    $response->assertDontSee('Blast WhatsApp Report to All Class Teachers');
    $response->assertDontSee('WhatsApp Guru');
    $response->assertDontSee('View WhatsApp Queue');
    $response->assertDontSee('Himpunan PDF Semua Kelas');
});

it('allows system admin to see whatsapp actions on class progress page', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Angsana',
        'phone' => '0123001000',
        'email_verified_at' => now(),
    ]);

    FamilyBilling::query()->create([
        'family_code' => 'SSP-CPG2',
        'billing_year' => (int) now()->year,
        'fee_amount' => 100,
        'paid_amount' => 100,
        'status' => 'paid',
    ]);

    Student::query()->create([
        'student_no' => 'CPG-0002',
        'family_code' => 'SSP-CPG2',
        'full_name' => 'Danish Irfan',
        'class_name' => '1 Angsana',
        'parent_name' => 'Puan Niza',
        'parent_phone' => '0123456789',
        'status' => 'active',
        'billing_year' => (int) now()->year,
    ]);

    $response = $this->actingAs($admin)->get(route('teacher.class-progress'));

    $response->assertOk();
    $response->assertSee('Blast WhatsApp Report to All Class Teachers');
    $response->assertSee('WhatsApp Guru');
    $response->assertSee('View WhatsApp Queue');
    $response->assertSee('Himpunan PDF Semua Kelas');
    $response->assertSee(route('admin.classes.pdf-archive', [
        'billing_year' => (int) now()->year,
    ]), false);
});

it('allows system admin to download one class pdf per file in a zip archive', function () {
    $admin = User::factory()->create([
        'role' => 'system_admin',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    foreach ([
        ['class_name' => '1 Angsana', 'family_code' => 'SSP-ZIP-001', 'student_no' => 'ZIP-0001'],
        ['class_name' => '2 Bestari', 'family_code' => 'SSP-ZIP-002', 'student_no' => 'ZIP-0002'],
    ] as $index => $row) {
        FamilyBilling::query()->create([
            'family_code' => $row['family_code'],
            'billing_year' => $billingYear,
            'fee_amount' => 100,
            'paid_amount' => $index === 0 ? 100 : 0,
            'status' => $index === 0 ? 'paid' : 'pending',
        ]);

        Student::query()->create([
            'student_no' => $row['student_no'],
            'family_code' => $row['family_code'],
            'full_name' => $index === 0 ? 'Aina Sofea' : 'Danish Irfan',
            'class_name' => $row['class_name'],
            'parent_name' => 'Penjaga Ujian',
            'parent_phone' => '0123456789',
            'status' => 'active',
            'billing_year' => $billingYear,
        ]);
    }

    $response = $this->actingAs($admin)->get(route('admin.classes.pdf-archive', [
        'billing_year' => $billingYear,
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/zip');
    expect($response->headers->get('content-disposition'))
        ->toContain("laporan-kutipan-pibg-semua-kelas-{$billingYear}-");

    $archivePath = $response->baseResponse->getFile()->getPathname();
    $archive = new ZipArchive;

    try {
        expect($archive->open($archivePath))->toBeTrue();
        expect($archive->numFiles)->toBe(2);

        $filenames = collect(range(0, $archive->numFiles - 1))
            ->map(fn (int $index): string => (string) $archive->getNameIndex($index))
            ->sort()
            ->values();

        expect($filenames->all())->toBe([
            "01-laporan-kutipan-pibg-1-angsana-{$billingYear}.pdf",
            "02-laporan-kutipan-pibg-2-bestari-{$billingYear}.pdf",
        ]);

        foreach ($filenames as $filename) {
            expect($archive->getFromName($filename))->toStartWith('%PDF-');
        }
    } finally {
        $archive->close();

        if (is_file($archivePath)) {
            unlink($archivePath);
        }
    }
});

it('blocks teachers from downloading the all-classes pdf archive', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'class_name' => '1 Angsana',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($teacher)->get(route('admin.classes.pdf-archive', [
        'billing_year' => (int) now()->year,
    ]));

    $response->assertForbidden();
});

it('downloads a class payment report pdf with authorised details', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'class_name' => '2 Bestari',
        'email_verified_at' => now(),
    ]);

    $billingYear = (int) now()->year;

    foreach ([
        [
            'family_code' => 'SSP-PDF-PAID',
            'paid_amount' => 100,
            'status' => 'paid',
            'student_no' => 'PDF-0001',
            'full_name' => 'Ali Imran',
        ],
        [
            'family_code' => 'SSP-PDF-UNPAID',
            'paid_amount' => 0,
            'status' => 'pending',
            'student_no' => 'PDF-0002',
            'full_name' => 'Siti Hawa',
        ],
    ] as $row) {
        FamilyBilling::query()->create([
            'family_code' => $row['family_code'],
            'billing_year' => $billingYear,
            'fee_amount' => 100,
            'paid_amount' => $row['paid_amount'],
            'status' => $row['status'],
        ]);

        Student::query()->create([
            'student_no' => $row['student_no'],
            'family_code' => $row['family_code'],
            'full_name' => $row['full_name'],
            'class_name' => '2 Bestari',
            'parent_name' => 'Penjaga '.$row['full_name'],
            'parent_phone' => '0123456789',
            'status' => 'active',
            'billing_year' => $billingYear,
        ]);
    }

    $response = $this->actingAs($teacher)->get(route('teacher.class-progress.pdf', [
        'class' => '2 Bestari',
        'billing_year' => $billingYear,
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))
        ->toContain("laporan-kutipan-pibg-2-bestari-{$billingYear}.pdf");
    expect($response->getContent())->toStartWith('%PDF-');
});

it('blocks pta users from downloading class payment detail pdfs', function () {
    $pta = User::factory()->create([
        'role' => 'pta',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($pta)->get(route('teacher.class-progress.pdf', [
        'class' => '1 Angsana',
        'billing_year' => (int) now()->year,
    ]));

    $response->assertForbidden();
});

it('blocks parent from class progress page', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($parent)->get(route('teacher.class-progress'));

    $response->assertForbidden();
});
