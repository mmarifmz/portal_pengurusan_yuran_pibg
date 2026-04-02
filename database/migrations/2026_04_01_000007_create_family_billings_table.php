<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_billings', function (Blueprint $table) {
            $table->id();
            $table->string('family_code');
            $table->unsignedSmallInteger('billing_year');
            $table->decimal('fee_amount', 10, 2)->default(100);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['family_code', 'billing_year']);
            $table->index(['billing_year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_billings');
    }
};