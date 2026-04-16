<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_student_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('student_no')->nullable()->index();
            $table->string('family_code')->index();
            $table->string('student_name');
            $table->string('class_name')->nullable();
            $table->unsignedSmallInteger('source_year')->index();
            $table->string('payment_status')->default('paid')->index();
            $table->decimal('amount_due', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('donation_amount', 10, 2)->default(0);
            $table->string('payment_reference')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['source_year', 'family_code', 'student_name', 'payment_reference'], 'legacy_payment_unique_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_student_payments');
    }
};

