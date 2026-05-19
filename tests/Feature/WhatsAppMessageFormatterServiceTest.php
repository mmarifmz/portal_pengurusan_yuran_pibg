<?php

use App\Services\WhatsApp\MessageFormatterService;
use Illuminate\Support\Collection;

it('builds summary message with whatsapp formatting and emojis', function () {
    $service = app(MessageFormatterService::class);

    $messages = $service->buildSummaryMessage(
        className: '4 ANGGERIK',
        teacherName: 'CIKGU KAREN',
        totalStudents: 34,
        paidCount: 28,
        unpaidCount: 6,
        paymentPercentage: 80.77,
        pibgAmount: 2100.00,
        additionalDonation: 620.00,
        totalCollected: 2720.00,
        expectedAmount: 2600.00,
        rank: 1,
        paidStudents: collect([['student_name' => 'AHMAD', 'amount_paid' => 100.00]]),
    );

    $body = $messages[0]['body'];

    expect($body)->toContain('📊 *Ringkasan Yuran & Sumbangan PIBG*');
    expect($body)->toContain('Assalamualaikum / Salam Sejahtera *CIKGU KAREN*,');
    expect($body)->toContain('Berikut adalah status kutipan semasa bagi kelas *4 ANGGERIK* 🏫');
    expect($body)->toContain('📈 *Peratus Bayaran:* *80.77%*');
    expect($body)->toContain('🧾 *Jumlah Kutipan:* *RM 2,720.00*');
    expect($body)->toContain('🏆 *Ranking Semasa:* #1');
    expect($body)->toContain("\n\n");
    expect($body)->not->toContain('<strong>');
    expect($body)->not->toContain('<br>');
    expect($body)->not->toContain('<div>');
});

it('builds paid list message with readable spacing', function () {
    $service = app(MessageFormatterService::class);

    $messages = $service->buildPaidListMessage('4 ANGGERIK', collect([
        ['student_name' => 'AHMAD HAKIMI', 'amount_paid' => 100.00],
        ['student_name' => 'NUR IRDINA', 'amount_paid' => 150.00],
    ]));

    $body = $messages[0]['body'];

    expect($body)->toContain('✅ *4 ANGGERIK - Senarai Telah Bayar*');
    expect($body)->toContain('1. AHMAD HAKIMI');
    expect($body)->toContain('2. NUR IRDINA');
    expect($body)->toContain("\n\n2. NUR IRDINA");
    expect($body)->not->toContain('RM 100.00');
    expect($body)->not->toContain('💵');
});

it('builds empty paid and unpaid list messages safely', function () {
    $service = app(MessageFormatterService::class);

    $paidMessages = $service->buildPaidListMessage('4 ANGGERIK', collect());
    $unpaidMessages = $service->buildUnpaidListMessage('4 ANGGERIK', collect());

    expect($paidMessages[0]['body'])->toContain('Tiada rekod bayaran setakat ini.');
    expect($unpaidMessages[0]['body'])->toContain('🎉 Semua murid telah membuat bayaran. Terima kasih cikgu.');
});

it('preserves unicode emojis and line breaks when messages are chunked', function () {
    $service = new MessageFormatterService(120);

    $paidStudents = Collection::times(8, fn (int $index): array => [
        'student_name' => 'PELAJAR '.$index,
        'amount_paid' => 100 + $index,
    ]);

    $messages = $service->buildPaidListMessage('4 ANGGERIK', $paidStudents);

    expect(count($messages))->toBeGreaterThan(1);
    expect($messages[0]['body'])->toContain('✅ *4 ANGGERIK - Senarai Telah Bayar*');
    expect($messages[0]['body'])->toContain("\n");
});
