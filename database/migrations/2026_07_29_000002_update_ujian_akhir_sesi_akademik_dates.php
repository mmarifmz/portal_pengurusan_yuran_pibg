<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_calendar_events')
            ->where('title', 'Ujian Akhir Sesi Akademik 2026')
            ->where('start_date', '2026-09-28')
            ->where('end_date', '2026-10-06')
            ->update([
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-16',
                'day_label' => 'Isnin – Jumaat',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('school_calendar_events')
            ->where('title', 'Ujian Akhir Sesi Akademik 2026')
            ->where('start_date', '2026-10-05')
            ->where('end_date', '2026-10-16')
            ->update([
                'start_date' => '2026-09-28',
                'end_date' => '2026-10-06',
                'day_label' => 'Isnin – Selasa',
                'updated_at' => now(),
            ]);
    }
};
