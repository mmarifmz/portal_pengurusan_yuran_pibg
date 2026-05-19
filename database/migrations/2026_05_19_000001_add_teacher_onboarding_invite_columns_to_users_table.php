<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'onboarding_invite_generated_at')) {
                $table->timestamp('onboarding_invite_generated_at')->nullable()->after('invite_status');
            }

            if (! Schema::hasColumn('users', 'onboarding_invite_sent_manually_at')) {
                $table->timestamp('onboarding_invite_sent_manually_at')->nullable()->after('onboarding_invite_generated_at');
            }

            if (! Schema::hasColumn('users', 'onboarding_invite_sent_by')) {
                $table->foreignId('onboarding_invite_sent_by')->nullable()->after('onboarding_invite_sent_manually_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'onboarding_invite_method')) {
                $table->string('onboarding_invite_method', 30)->nullable()->after('onboarding_invite_sent_by');
            }

            if (! Schema::hasColumn('users', 'onboarding_invite_status')) {
                $table->string('onboarding_invite_status', 30)->default('not_generated')->after('onboarding_invite_method')->index();
            }
        });

        DB::table('users')
            ->whereNull('onboarding_invite_status')
            ->update(['onboarding_invite_status' => 'not_generated']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'onboarding_invite_status')) {
                $table->dropIndex(['onboarding_invite_status']);
                $table->dropColumn('onboarding_invite_status');
            }

            if (Schema::hasColumn('users', 'onboarding_invite_method')) {
                $table->dropColumn('onboarding_invite_method');
            }

            if (Schema::hasColumn('users', 'onboarding_invite_sent_by')) {
                $table->dropConstrainedForeignId('onboarding_invite_sent_by');
            }

            if (Schema::hasColumn('users', 'onboarding_invite_sent_manually_at')) {
                $table->dropColumn('onboarding_invite_sent_manually_at');
            }

            if (Schema::hasColumn('users', 'onboarding_invite_generated_at')) {
                $table->dropColumn('onboarding_invite_generated_at');
            }
        });
    }
};
