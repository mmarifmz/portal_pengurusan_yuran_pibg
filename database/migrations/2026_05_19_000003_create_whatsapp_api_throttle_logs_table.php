<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_api_throttle_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('app_name')->index();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->string('recipient_phone', 25)->nullable()->index();
            $table->timestamp('sent_at')->index();
            $table->string('api_response_status')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_api_throttle_logs');
    }
};
