<?php

use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->timestamp('transferred_at')->nullable()->after('status');
            $table->foreignId('transferred_by')->nullable()->after('transferred_at')->constrained('users')->nullOnDelete();
            $table->text('transfer_note')->nullable()->after('transferred_by');
            $table->index('status');
        });

        DB::table('students')
            ->whereNull('status')
            ->update(['status' => Student::STATUS_ACTIVE]);
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transferred_by');
            $table->dropColumn(['transferred_at', 'transfer_note']);
            $table->dropIndex(['status']);
        });
    }
};
