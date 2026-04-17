<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('family_billing_phones')) {
            return;
        }

        Schema::create('family_billing_phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_billing_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 25);
            $table->string('normalized_phone', 25);
            $table->timestamps();

            $table->unique(['family_billing_id', 'normalized_phone'], 'fbp_family_phone_unique');
            $table->index('normalized_phone', 'fbp_normalized_phone_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_billing_phones');
    }
};