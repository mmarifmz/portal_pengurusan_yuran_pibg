<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_payment_tester')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_payment_tester')->default(false)->after('class_name');
                $table->index('is_payment_tester');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_payment_tester')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['is_payment_tester']);
                $table->dropColumn('is_payment_tester');
            });
        }
    }
};
