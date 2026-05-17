<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_payment_installment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_billing_id')->constrained()->cascadeOnDelete();
            $table->string('billcode')->nullable()->index();
            $table->string('order_id')->nullable()->index();
            $table->string('allocation_type', 32)->index();
            $table->decimal('amount', 10, 2);
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['family_billing_id', 'allocation_type', 'status'], 'payment_allocations_family_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
