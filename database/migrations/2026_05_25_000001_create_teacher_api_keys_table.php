<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 24)->default('pibg_live');
            $table->string('last_four', 4);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('total_calls')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_api_keys');
    }
};
