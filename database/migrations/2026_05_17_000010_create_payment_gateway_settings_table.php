<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_gateway_settings')) {
            return;
        }

        Schema::create('payment_gateway_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enable_fpx')->default(true);
            $table->boolean('enable_duitnow_qr')->default(false);
            $table->boolean('charge_duitnow_qr_to_customer')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
