<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_billing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_provider')->default('toyyibpay');
            $table->string('external_order_id')->unique();
            $table->string('provider_bill_code')->nullable()->index();
            $table->string('provider_ref_no')->nullable()->index();
            $table->string('provider_invoice_no')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('fee_amount_paid', 10, 2)->default(0);
            $table->decimal('donation_amount', 10, 2)->default(0);
            $table->string('payer_email')->nullable();
            $table->string('payer_phone')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('status_reason')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->json('raw_return')->nullable();
            $table->json('raw_callback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_payment_transactions');
    }
};
