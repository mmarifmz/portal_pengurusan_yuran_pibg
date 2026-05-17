<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_billings', function (Blueprint $table) {
            if (! Schema::hasColumn('family_billings', 'social_tag')) {
                $table->string('social_tag')->nullable()->after('status');
                $table->index('social_tag');
            }
        });
    }

    public function down(): void
    {
        Schema::table('family_billings', function (Blueprint $table) {
            if (Schema::hasColumn('family_billings', 'social_tag')) {
                $table->dropIndex(['social_tag']);
                $table->dropColumn('social_tag');
            }
        });
    }
};
