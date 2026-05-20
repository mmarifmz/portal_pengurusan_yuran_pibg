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
            if (! Schema::hasColumn('users', 'access_block_reason')) {
                $table->text('access_block_reason')->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('users', 'parent_access_reset_at')) {
                $table->timestamp('parent_access_reset_at')->nullable()->after('access_block_reason');
            }
        });

        if (Schema::hasTable('roles')) {
            $now = now();

            DB::table('roles')->insertOrIgnore([
                [
                    'name' => 'admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'super_admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $dropColumns = [];

            foreach (['parent_access_reset_at', 'access_block_reason'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
