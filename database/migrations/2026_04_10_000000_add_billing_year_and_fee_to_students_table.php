<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'billing_year')) {
                $table->unsignedSmallInteger('billing_year')
                    ->after('family_code')
                    ->default((int) now()->format('Y'));
            }

            if (! Schema::hasColumn('students', 'annual_fee')) {
                $table->decimal('annual_fee', 10, 2)
                    ->after('paid_amount')
                    ->default(100.00);
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'annual_fee')) {
                $table->dropColumn('annual_fee');
            }

            if (Schema::hasColumn('students', 'billing_year')) {
                $table->dropColumn('billing_year');
            }
        });
    }
};
