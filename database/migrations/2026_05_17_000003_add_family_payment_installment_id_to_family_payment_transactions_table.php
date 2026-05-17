<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('family_payment_transactions', 'family_payment_installment_id')) {
            Schema::table('family_payment_transactions', function (Blueprint $table) {
                $table->foreignId('family_payment_installment_id')
                    ->nullable()
                    ->after('family_billing_id');
            });
        }

        if (! $this->foreignKeyExists('family_payment_transactions', 'fpt_installment_fk')) {
            Schema::table('family_payment_transactions', function (Blueprint $table) {
                $table->foreign('family_payment_installment_id', 'fpt_installment_fk')
                    ->references('id')
                    ->on('family_payment_installments')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('family_payment_transactions', function (Blueprint $table) {
            if ($this->foreignKeyExists('family_payment_transactions', 'fpt_installment_fk')) {
                $table->dropForeign('fpt_installment_fk');
            }

            if (Schema::hasColumn('family_payment_transactions', 'family_payment_installment_id')) {
                $table->dropColumn('family_payment_installment_id');
            }
        });
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $databaseName = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', $databaseName)
            ->where('TABLE_NAME', $tableName)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
