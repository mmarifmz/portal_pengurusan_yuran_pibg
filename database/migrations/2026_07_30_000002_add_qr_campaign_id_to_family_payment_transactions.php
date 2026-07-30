<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_payment_transactions', function (Blueprint $table): void {
            $table->foreignId('qr_campaign_id')
                ->nullable()
                ->after('user_id')
                ->constrained('qr_campaigns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('family_payment_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('qr_campaign_id');
        });
    }
};
