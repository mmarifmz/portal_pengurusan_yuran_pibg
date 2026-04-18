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
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_payment_tester');
                $table->index('is_active');
            }

            if (! Schema::hasColumn('users', 'receive_whatsapp_notifications')) {
                $table->boolean('receive_whatsapp_notifications')->default(false)->after('is_active');
                $table->index('receive_whatsapp_notifications');
            }
        });

        DB::table('users')
            ->where('role', 'teacher')
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->update(['receive_whatsapp_notifications' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'receive_whatsapp_notifications')) {
                $table->dropIndex(['receive_whatsapp_notifications']);
                $table->dropColumn('receive_whatsapp_notifications');
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            }
        });
    }
};
