<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'is_duplicate')) {
                $table->boolean('is_duplicate')->default(false)->after('class_name');
            }
        });

        $duplicateIds = DB::table('students')
            ->select('id', 'full_name', 'class_name')
            ->get()
            ->groupBy(function (object $student): string {
                $fullName = strtolower(trim((string) $student->full_name));
                $className = strtolower(trim((string) ($student->class_name ?? '')));

                return "{$fullName}|{$className}";
            })
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->flatMap(fn (Collection $group): Collection => $group->pluck('id'))
            ->values();

        DB::table('students')->update(['is_duplicate' => false]);

        if ($duplicateIds->isNotEmpty()) {
            DB::table('students')
                ->whereIn('id', $duplicateIds->all())
                ->update(['is_duplicate' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'is_duplicate')) {
                $table->dropColumn('is_duplicate');
            }
        });
    }
};
