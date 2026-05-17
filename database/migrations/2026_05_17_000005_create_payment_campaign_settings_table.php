<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_campaign_settings', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_name');
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('allow_single_payment')->default(true);
            $table->boolean('allow_split_payment')->default(false);
            $table->boolean('allow_split_2')->default(false);
            $table->string('split_2_visibility', 32)->default('all');
            $table->string('split_2_social_tag')->nullable();
            $table->boolean('allow_split_3')->default(false);
            $table->string('split_3_visibility', 32)->default('all');
            $table->string('split_3_social_tag')->nullable();
            $table->dateTime('effective_from')->nullable()->index();
            $table->dateTime('effective_until')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_campaign_settings');
    }
};
