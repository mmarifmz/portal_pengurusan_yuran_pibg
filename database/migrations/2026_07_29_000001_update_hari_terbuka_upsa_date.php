<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_calendar_events')
            ->where('title', 'Hari Terbuka 1')
            ->where('start_date', '2026-06-19')
            ->update([
                'title' => 'Hari Terbuka UPSA',
                'start_date' => '2026-07-31',
                'description' => 'Hari Terbuka UPSA',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('school_calendar_events')
            ->where('title', 'Hari Terbuka UPSA')
            ->where('start_date', '2026-07-31')
            ->update([
                'title' => 'Hari Terbuka 1',
                'start_date' => '2026-06-19',
                'description' => 'Hari Terbuka 1',
                'updated_at' => now(),
            ]);
    }
};
