<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogathon_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('jogathon_campaigns')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('jogathon_participants')->restrictOnDelete();
            $table->foreignId('cause_id')->nullable()->constrained('jogathon_causes')->restrictOnDelete();
            $table->string('source', 32)->index();
            $table->bigInteger('amount_sen');
            $table->bigInteger('distance_cm');
            $table->string('status', 32)->index();
            $table->string('donor_display_name', 120)->nullable();
            $table->boolean('is_anonymous_public')->default(false);
            $table->string('encouragement_message', 280)->nullable();
            $table->boolean('is_message_approved')->default(false);
            $table->string('external_order_id')->nullable()->unique();
            $table->string('provider_bill_code')->nullable()->unique();
            $table->string('provider_reference')->nullable()->unique();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamp('finalised_at')->nullable()->index();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('original_contribution_id')->nullable()->constrained('jogathon_contributions')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status', 'source'], 'jogathon_contributions_campaign_status_source_idx');
            $table->index(['participant_id', 'status', 'finalised_at'], 'jogathon_contributions_participant_status_final_idx');
            $table->index(['cause_id', 'status'], 'jogathon_contributions_cause_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogathon_contributions');
    }
};
