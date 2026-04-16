<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('day_label')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('school_calendar_events')->insert([
            [
                'title' => 'Kejohanan Merentas Desa',
                'start_date' => '2026-01-23',
                'end_date' => null,
                'day_label' => 'Jumaat',
                'description' => 'Kejohanan Merentas Desa',
                'notes' => null,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Mesyuarat Agung PIBG Ke-55',
                'start_date' => '2026-02-28',
                'end_date' => null,
                'day_label' => 'Sabtu',
                'description' => 'Mesyuarat Agung PIBG Ke-55',
                'notes' => null,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Majlis Berbuka Puasa & Penyerahan Sumbangan',
                'start_date' => '2026-03-11',
                'end_date' => null,
                'day_label' => 'Rabu',
                'description' => 'Majlis Berbuka Puasa & Penyerahan Sumbangan',
                'notes' => null,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Cuti Tambahan KPM Hari Raya & Cuti Penggal 1 Sesi 2026',
                'start_date' => '2026-03-19',
                'end_date' => '2026-03-29',
                'day_label' => '-',
                'description' => 'Cuti Tambahan KPM Hari Raya & Cuti Penggal 1 Sesi 2026',
                'notes' => null,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Sambutan Hari Raya Aidilfitri & Sambutan Hari Lahir (Januari – April)',
                'start_date' => '2026-04-17',
                'end_date' => null,
                'day_label' => 'Jumaat',
                'description' => 'Sambutan Hari Raya Aidilfitri & Sambutan Hari Lahir (Januari – April)',
                'notes' => null,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Ujian Pertengahan Sesi Akademik 2026 (Tahap 2)',
                'start_date' => '2026-05-04',
                'end_date' => '2026-05-12',
                'day_label' => 'Isnin – Selasa',
                'description' => 'Ujian Pertengahan Sesi Akademik 2026 (Tahap 2)',
                'notes' => null,
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Cuti Pertengahan Tahun 2026',
                'start_date' => '2026-05-23',
                'end_date' => '2026-06-07',
                'day_label' => '-',
                'description' => 'Cuti Pertengahan Tahun 2026',
                'notes' => null,
                'sort_order' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Hari Terbuka 1',
                'start_date' => '2026-06-19',
                'end_date' => null,
                'day_label' => 'Jumaat',
                'description' => 'Hari Terbuka 1',
                'notes' => null,
                'sort_order' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Kejohanan Sukan Tahunan Sekolah Ke-53',
                'start_date' => '2026-07-25',
                'end_date' => null,
                'day_label' => 'Sabtu',
                'description' => 'Kejohanan Sukan Tahunan Sekolah Ke-53',
                'notes' => null,
                'sort_order' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Perkhemahan Perdana Unit Beruniform',
                'start_date' => '2026-08-21',
                'end_date' => '2026-08-23',
                'day_label' => 'Jumaat – Ahad',
                'description' => 'Perkhemahan Perdana Unit Beruniform',
                'notes' => 'Tertakluk kepada perubahan',
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Cuti Penggal 2 Sesi 2026',
                'start_date' => '2026-08-29',
                'end_date' => '2026-09-06',
                'day_label' => '-',
                'description' => 'Cuti Penggal 2 Sesi 2026',
                'notes' => null,
                'sort_order' => 11,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Sambutan Hari Lahir (Mei – September)',
                'start_date' => '2026-09-25',
                'end_date' => null,
                'day_label' => 'Jumaat',
                'description' => 'Sambutan Hari Lahir (Mei – September)',
                'notes' => null,
                'sort_order' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Ujian Akhir Sesi Akademik 2026',
                'start_date' => '2026-09-28',
                'end_date' => '2026-10-06',
                'day_label' => 'Isnin – Selasa',
                'description' => 'Ujian Akhir Sesi Akademik 2026',
                'notes' => 'Tertakluk kepada perubahan/arahan KPM',
                'sort_order' => 13,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Program PIBG',
                'start_date' => '2026-10-24',
                'end_date' => null,
                'day_label' => 'Sabtu',
                'description' => 'Program PIBG',
                'notes' => 'Tertakluk kepada perubahan',
                'sort_order' => 14,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Majlis Apresiasi Kokurikulum (Dalaman)',
                'start_date' => '2026-10-28',
                'end_date' => null,
                'day_label' => 'Rabu',
                'description' => 'Majlis Apresiasi Kokurikulum (Dalaman)',
                'notes' => null,
                'sort_order' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Hari Terbuka 2',
                'start_date' => '2026-11-13',
                'end_date' => null,
                'day_label' => 'Jumaat',
                'description' => 'Hari Terbuka 2',
                'notes' => null,
                'sort_order' => 16,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Sambutan Hari Kanak-Kanak & Sambutan Hari Lahir (Oktober – Disember)',
                'start_date' => '2026-11-20',
                'end_date' => null,
                'day_label' => 'Jumaat',
                'description' => 'Sambutan Hari Kanak-Kanak & Sambutan Hari Lahir (Oktober – Disember)',
                'notes' => null,
                'sort_order' => 17,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Majlis Apresiasi Prasekolah dan Tahun 6',
                'start_date' => '2026-11-28',
                'end_date' => null,
                'day_label' => 'Sabtu',
                'description' => 'Majlis Apresiasi Prasekolah dan Tahun 6',
                'notes' => null,
                'sort_order' => 18,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Majlis Apresiasi Akademik Tahun 2026',
                'start_date' => '2026-12-01',
                'end_date' => null,
                'day_label' => 'Selasa',
                'description' => 'Majlis Apresiasi Akademik Tahun 2026',
                'notes' => 'Tertakluk kepada perubahan/arahan KPM',
                'sort_order' => 19,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Cuti Akhir Persekolahan Sesi 2026',
                'start_date' => '2026-12-05',
                'end_date' => '2027-01-03',
                'day_label' => '-',
                'description' => 'Cuti Akhir Persekolahan Sesi 2026',
                'notes' => null,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('school_calendar_events');
    }
};
