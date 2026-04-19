<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_b40')->default(false)->after('import_raw_line');
            $table->boolean('is_kwap')->default(false)->after('is_b40');
            $table->boolean('is_rmt')->default(false)->after('is_kwap');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['is_b40', 'is_kwap', 'is_rmt']);
        });
    }
};
