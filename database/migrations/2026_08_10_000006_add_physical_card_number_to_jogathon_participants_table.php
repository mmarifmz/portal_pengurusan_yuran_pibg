<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogathon_participants', function (Blueprint $table): void {
            $table->string('physical_card_number', 32)->nullable()->unique()->after('public_slug');
        });
    }

    public function down(): void
    {
        Schema::table('jogathon_participants', function (Blueprint $table): void {
            $table->dropUnique(['physical_card_number']);
            $table->dropColumn('physical_card_number');
        });
    }
};
