<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_payment_transactions', function (Blueprint $table) {
            $table->text('donation_intention')->nullable()->after('payer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('family_payment_transactions', function (Blueprint $table) {
            $table->dropColumn('donation_intention');
        });
    }
};
