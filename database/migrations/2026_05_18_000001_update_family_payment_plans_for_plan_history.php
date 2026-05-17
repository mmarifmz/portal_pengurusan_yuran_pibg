<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('family_payment_plans')) {
            return;
        }

        Schema::table('family_payment_plans', function (Blueprint $table) {
            if (! $this->indexExists('family_payment_plans', 'family_payment_plans_family_billing_id_status_index')) {
                $table->index(['family_billing_id', 'status']);
            }
        });

        Schema::table('family_payment_plans', function (Blueprint $table) {
            if ($this->indexExists('family_payment_plans', 'family_payment_plans_family_billing_id_unique')) {
                $table->dropUnique('family_payment_plans_family_billing_id_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('family_payment_plans')) {
            return;
        }

        Schema::table('family_payment_plans', function (Blueprint $table) {
            if (! $this->indexExists('family_payment_plans', 'family_payment_plans_family_billing_id_unique')) {
                $table->unique('family_billing_id');
            }
        });

        Schema::table('family_payment_plans', function (Blueprint $table) {
            if ($this->indexExists('family_payment_plans', 'family_payment_plans_family_billing_id_status_index')) {
                $table->dropIndex('family_payment_plans_family_billing_id_status_index');
            }
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$tableName}')");

            return collect($indexes)->contains(fn ($index) => ($index->name ?? null) === $indexName);
        }

        $databaseName = DB::getDatabaseName();

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $databaseName)
            ->where('TABLE_NAME', $tableName)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
