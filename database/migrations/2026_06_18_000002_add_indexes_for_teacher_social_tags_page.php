<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $this->indexIfMissing($table, 'students', ['billing_year', 'class_name'], 'students_year_class_idx');
            $this->indexIfMissing($table, 'students', ['billing_year', 'family_code'], 'students_year_family_idx');
            $this->indexIfMissing($table, 'students', ['billing_year', 'is_b40'], 'students_year_b40_idx');
            $this->indexIfMissing($table, 'students', ['billing_year', 'is_kwap'], 'students_year_kwap_idx');
            $this->indexIfMissing($table, 'students', ['billing_year', 'is_rmt'], 'students_year_rmt_idx');
        });

        Schema::table('family_billings', function (Blueprint $table) {
            $this->indexIfMissing($table, 'family_billings', ['billing_year', 'family_code'], 'family_billings_year_family_idx');
        });

        Schema::table('family_social_tags', function (Blueprint $table) {
            $this->indexIfMissing($table, 'family_social_tags', ['social_tag_id', 'family_billing_id'], 'family_social_tags_tag_family_idx');
        });
    }

    public function down(): void
    {
        Schema::table('family_social_tags', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'family_social_tags', 'family_social_tags_tag_family_idx');
        });

        Schema::table('family_billings', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'family_billings', 'family_billings_year_family_idx');
        });

        Schema::table('students', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'students', 'students_year_rmt_idx');
            $this->dropIndexIfExists($table, 'students', 'students_year_kwap_idx');
            $this->dropIndexIfExists($table, 'students', 'students_year_b40_idx');
            $this->dropIndexIfExists($table, 'students', 'students_year_family_idx');
            $this->dropIndexIfExists($table, 'students', 'students_year_class_idx');
        });
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function indexIfMissing(Blueprint $table, string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasIndex($tableName, $indexName)) {
            $table->index($columns, $indexName);
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $indexName): void
    {
        if (Schema::hasIndex($tableName, $indexName)) {
            $table->dropIndex($indexName);
        }
    }
};
