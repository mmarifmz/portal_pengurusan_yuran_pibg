<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_payment_transactions', function (Blueprint $table): void {
            $table->uuid('receipt_uuid')->nullable()->unique()->after('external_order_id');
            $table->string('receipt_message_id')->nullable()->after('provider_invoice_no');
            $table->timestamp('receipt_notified_at')->nullable()->after('paid_at');
        });

        DB::table('family_payment_transactions')
            ->whereNull('receipt_uuid')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $transaction): void {
                DB::table('family_payment_transactions')
                    ->where('id', $transaction->id)
                    ->update(['receipt_uuid' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        Schema::table('family_payment_transactions', function (Blueprint $table): void {
            $table->dropColumn([
                'receipt_uuid',
                'receipt_message_id',
                'receipt_notified_at',
            ]);
        });
    }
};
