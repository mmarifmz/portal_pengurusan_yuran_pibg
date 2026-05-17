<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_payment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_billing_id')->constrained()->cascadeOnDelete();
            $table->string('plan_type', 32)->default('full');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance_amount', 10, 2);
            $table->string('status', 32)->default('pending')->index();
            $table->boolean('allow_admin_override')->default(false);
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->unique('family_billing_id');
            $table->index(['plan_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_payment_plans');
    }
};
