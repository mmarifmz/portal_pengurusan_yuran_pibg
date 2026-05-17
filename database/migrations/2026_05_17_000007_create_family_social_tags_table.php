<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_social_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_billing_id')->constrained('family_billings')->cascadeOnDelete();
            $table->foreignId('social_tag_id')->constrained('social_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['family_billing_id', 'social_tag_id'], 'family_social_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_social_tags');
    }
};
