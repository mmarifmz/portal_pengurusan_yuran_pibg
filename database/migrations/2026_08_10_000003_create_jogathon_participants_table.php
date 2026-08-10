<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogathon_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('jogathon_campaigns')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('public_slug')->unique();
            $table->string('public_display_name');
            $table->string('class_name_snapshot')->nullable()->index();
            $table->unsignedBigInteger('target_amount_sen');
            $table->unsignedBigInteger('target_distance_cm');
            $table->boolean('is_eligible')->default(true)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->boolean('participation_opt_out')->default(false)->index();
            $table->timestamp('enrolled_at');
            $table->timestamp('withdrawn_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['campaign_id', 'student_id']);
            $table->index(['campaign_id', 'class_name_snapshot', 'is_eligible'], 'jogathon_participants_campaign_class_eligible_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogathon_participants');
    }
};
