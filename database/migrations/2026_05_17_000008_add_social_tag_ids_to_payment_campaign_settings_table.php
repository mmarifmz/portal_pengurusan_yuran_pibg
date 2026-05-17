<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_campaign_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_campaign_settings', 'split_2_social_tag_id')) {
                $table->foreignId('split_2_social_tag_id')
                    ->nullable()
                    ->after('split_2_social_tag')
                    ->constrained('social_tags')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('payment_campaign_settings', 'split_3_social_tag_id')) {
                $table->foreignId('split_3_social_tag_id')
                    ->nullable()
                    ->after('split_3_social_tag')
                    ->constrained('social_tags')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_campaign_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payment_campaign_settings', 'split_2_social_tag_id')) {
                $table->dropConstrainedForeignId('split_2_social_tag_id');
            }

            if (Schema::hasColumn('payment_campaign_settings', 'split_3_social_tag_id')) {
                $table->dropConstrainedForeignId('split_3_social_tag_id');
            }
        });
    }
};
