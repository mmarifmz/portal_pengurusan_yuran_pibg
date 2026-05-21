<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_payment_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_id')->nullable()->constrained('family_billings')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('student_name')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('teacher_name')->nullable();
            $table->string('teacher_phone')->nullable();
            $table->string('class_name')->nullable();
            $table->foreignId('payment_flow_id')->nullable()->constrained('family_payment_transactions')->nullOnDelete();
            $table->string('order_id')->nullable();
            $table->string('bill_year')->nullable();
            $table->text('receipt_url')->nullable();
            $table->decimal('pibg_amount', 10, 2)->default(0);
            $table->decimal('donation_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->longText('message_body')->nullable();
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->longText('api_response')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('class_name');
            $table->index('teacher_id');
            $table->index('order_id');
            $table->index('queued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_payment_notifications');
    }
};
