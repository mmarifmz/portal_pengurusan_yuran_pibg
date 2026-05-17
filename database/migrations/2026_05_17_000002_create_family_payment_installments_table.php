<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_payment_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_payment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_billing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_no');
            $table->decimal('amount', 10, 2);
            $table->string('status', 32)->default('pending')->index();
            $table->string('billcode')->nullable()->index();
            $table->string('toyyibpay_refno')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['family_payment_plan_id', 'installment_no'], 'family_payment_installments_unique_number');
            $table->index(['family_billing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_payment_installments');
    }
};
