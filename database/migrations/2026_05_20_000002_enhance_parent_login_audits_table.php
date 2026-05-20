<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_login_audits', function (Blueprint $table): void {
            if (! Schema::hasColumn('parent_login_audits', 'action_type')) {
                $table->string('action_type', 60)->default('login')->after('normalized_phone');
            }

            if (! Schema::hasColumn('parent_login_audits', 'access_status')) {
                $table->string('access_status', 30)->default('successful')->after('action_type');
            }

            if (! Schema::hasColumn('parent_login_audits', 'page_visited')) {
                $table->string('page_visited')->nullable()->after('access_status');
            }

            if (! Schema::hasColumn('parent_login_audits', 'login_method')) {
                $table->string('login_method', 40)->nullable()->after('page_visited');
            }

            if (! Schema::hasColumn('parent_login_audits', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('login_method');
            }

            if (! Schema::hasColumn('parent_login_audits', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }

            if (! Schema::hasColumn('parent_login_audits', 'device_browser')) {
                $table->string('device_browser', 120)->nullable()->after('user_agent');
            }

            if (! Schema::hasColumn('parent_login_audits', 'family_billing_id')) {
                $table->foreignId('family_billing_id')->nullable()->after('device_browser')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('parent_login_audits', 'student_id')) {
                $table->foreignId('student_id')->nullable()->after('family_billing_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('parent_login_audits', 'space_key')) {
                $table->string('space_key', 40)->nullable()->after('student_id');
            }

            if (! Schema::hasColumn('parent_login_audits', 'occurred_at')) {
                $table->timestamp('occurred_at')->nullable()->after('logged_in_at');
            }

            if (! Schema::hasColumn('parent_login_audits', 'meta')) {
                $table->json('meta')->nullable()->after('occurred_at');
            }
        });

        DB::table('parent_login_audits')
            ->whereNull('occurred_at')
            ->update([
                'occurred_at' => DB::raw('logged_in_at'),
                'action_type' => DB::raw("COALESCE(action_type, 'login')"),
                'access_status' => DB::raw("COALESCE(access_status, 'successful')"),
            ]);
    }

    public function down(): void
    {
        Schema::table('parent_login_audits', function (Blueprint $table): void {
            $dropColumns = [];

            foreach ([
                'meta',
                'occurred_at',
                'space_key',
                'student_id',
                'family_billing_id',
                'device_browser',
                'user_agent',
                'ip_address',
                'login_method',
                'page_visited',
                'access_status',
                'action_type',
            ] as $column) {
                if (Schema::hasColumn('parent_login_audits', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (in_array('family_billing_id', $dropColumns, true)) {
                $table->dropConstrainedForeignId('family_billing_id');
                $dropColumns = array_values(array_diff($dropColumns, ['family_billing_id']));
            }

            if (in_array('student_id', $dropColumns, true)) {
                $table->dropConstrainedForeignId('student_id');
                $dropColumns = array_values(array_diff($dropColumns, ['student_id']));
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
