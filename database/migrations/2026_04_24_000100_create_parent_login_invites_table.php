<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_login_invites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_billing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 25);
            $table->string('normalized_phone', 25)->index();
            $table->string('token', 96)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['family_billing_id', 'normalized_phone'], 'pli_family_phone_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_login_invites');
    }
};
