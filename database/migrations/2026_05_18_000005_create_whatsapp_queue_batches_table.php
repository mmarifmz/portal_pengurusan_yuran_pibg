<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_queue_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_id')->unique();
            $table->string('message_type')->index();
            $table->foreignId('queued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('class_progress');
            $table->unsignedInteger('total_classes_selected')->default(0);
            $table->unsignedInteger('total_classes_queued')->default(0);
            $table->unsignedInteger('total_messages_queued')->default(0);
            $table->unsignedInteger('total_skipped')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_queue_batches');
    }
};
