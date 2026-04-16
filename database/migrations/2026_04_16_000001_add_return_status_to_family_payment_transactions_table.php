<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_payment_transactions', function (Blueprint $table): void {
            $table->string('return_status')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('family_payment_transactions', function (Blueprint $table): void {
            $table->dropColumn('return_status');
        });
    }
};
