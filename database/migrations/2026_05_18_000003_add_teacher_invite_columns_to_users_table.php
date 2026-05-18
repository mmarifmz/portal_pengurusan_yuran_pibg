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
            if (! Schema::hasColumn('users', 'teacher_invite_sent_at')) {
                $table->timestamp('teacher_invite_sent_at')->nullable()->after('receive_whatsapp_notifications');
            }

            if (! Schema::hasColumn('users', 'invite_status')) {
                $table->string('invite_status', 20)->default('pending')->after('teacher_invite_sent_at')->index();
            }
        });

        DB::table('users')
            ->where('role', 'teacher')
            ->whereNull('invite_status')
            ->update(['invite_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'invite_status')) {
                $table->dropIndex(['invite_status']);
                $table->dropColumn('invite_status');
            }

            if (Schema::hasColumn('users', 'teacher_invite_sent_at')) {
                $table->dropColumn('teacher_invite_sent_at');
            }
        });
    }
};
