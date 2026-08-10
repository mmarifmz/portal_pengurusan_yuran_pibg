<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogathon_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->unsignedBigInteger('default_target_amount_sen')->default(50_000);
            $table->unsignedBigInteger('default_target_distance_cm')->default(500_000);
            $table->boolean('show_class_publicly')->default(false);
            $table->boolean('allow_public_indexing')->default(false);
            $table->boolean('allow_unspecified_cause')->default(false);
            $table->json('year_to_tahap')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogathon_campaigns');
    }
};
