<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogathon_participant_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('jogathon_campaigns')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('jogathon_participants')->cascadeOnDelete();
            $table->string('source', 32)->index();
            $table->string('channel', 64)->nullable()->index();
            $table->string('access_point', 32)->index();
            $table->string('url', 500)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['campaign_id', 'source', 'occurred_at'], 'jogathon_visits_campaign_source_time_idx');
            $table->index(['participant_id', 'source', 'occurred_at'], 'jogathon_visits_participant_source_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogathon_participant_visits');
    }
};
