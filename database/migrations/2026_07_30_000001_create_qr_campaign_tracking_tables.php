<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('short_code', 16)->unique();
            $table->string('purpose', 32)->index();
            $table->string('destination_type', 40);
            $table->string('destination_path', 500);
            $table->foreignId('payment_campaign_setting_id')
                ->nullable()
                ->constrained('payment_campaign_settings')
                ->nullOnDelete();
            $table->string('class_name')->nullable()->index();
            $table->string('location_name')->nullable()->index();
            $table->string('distribution_channel')->nullable()->index();
            $table->string('poster_title');
            $table->string('poster_subtitle')->nullable();
            $table->string('call_to_action')->default('Imbas untuk teruskan');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('qr_campaign_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('qr_campaign_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scanned_at')->index();
            $table->string('visitor_hash', 64)->index();
            $table->string('user_agent', 500)->nullable();
            $table->string('referrer', 1000)->nullable();
            $table->timestamps();

            $table->index(['qr_campaign_id', 'scanned_at'], 'qr_scans_campaign_time_idx');
            $table->index(['qr_campaign_id', 'visitor_hash'], 'qr_scans_campaign_visitor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_campaign_scans');
        Schema::dropIfExists('qr_campaigns');
    }
};
