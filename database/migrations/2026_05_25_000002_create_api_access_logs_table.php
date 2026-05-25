<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('teacher_api_keys')->nullOnDelete();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->string('query_text')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedSmallInteger('response_status')->default(200);
            $table->unsignedInteger('result_count')->default(0);
            $table->text('error_message')->nullable();
            $table->unsignedInteger('execution_time_ms')->default(0);
            $table->timestamps();

            $table->index(['created_at', 'response_status']);
            $table->index(['teacher_id', 'created_at']);
            $table->index('endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_access_logs');
    }
};
