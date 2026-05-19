<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_message_queues', function (Blueprint $table): void {
            $table->timestamp('scheduled_at')->nullable()->after('queued_at')->index();
            $table->unsignedSmallInteger('part_order')->default(1)->after('total_parts');
        });

        DB::table('whatsapp_message_queues')
            ->whereNull('scheduled_at')
            ->update([
                'scheduled_at' => DB::raw('COALESCE(queued_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('whatsapp_message_queues', function (Blueprint $table): void {
            $table->dropColumn(['scheduled_at', 'part_order']);
        });
    }
};
