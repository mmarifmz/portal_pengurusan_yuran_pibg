<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_queues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('queue_batch_id')->nullable()->index();
            $table->unsignedSmallInteger('billing_year')->index();
            $table->string('class_name')->index();
            $table->foreignId('teacher_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_phone', 25)->index();
            $table->string('message_type')->index();
            $table->string('message_part');
            $table->unsignedSmallInteger('message_segment')->default(1);
            $table->unsignedSmallInteger('segment_count')->default(1);
            $table->unsignedSmallInteger('total_parts')->default(1);
            $table->text('message_body');
            $table->string('status')->default('pending')->index();
            $table->foreignId('queued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamp('sending_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_queues');
    }
};
