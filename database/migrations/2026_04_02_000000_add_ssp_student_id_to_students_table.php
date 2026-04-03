<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('ssp_student_id')->unique()->nullable()->after('family_code');
            $table->text('import_raw_line')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['ssp_student_id']);
            $table->dropColumn(['ssp_student_id', 'import_raw_line']);
        });
    }
};
