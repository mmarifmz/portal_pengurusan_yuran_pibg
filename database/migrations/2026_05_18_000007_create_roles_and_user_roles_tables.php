<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        $knownRoles = collect([
            'parent',
            'teacher',
            'super_teacher',
            'system_admin',
            'pta',
            'system_installer',
        ])
            ->merge(
                DB::table('users')
                    ->whereNotNull('role')
                    ->pluck('role')
                    ->map(fn ($role): string => trim((string) $role))
                    ->filter()
            )
            ->unique()
            ->values();

        $now = now();

        DB::table('roles')->insert(
            $knownRoles->map(fn (string $role): array => [
                'name' => $role,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        $roleIdsByName = DB::table('roles')->pluck('id', 'name');

        DB::table('users')
            ->select('id', 'role')
            ->orderBy('id')
            ->chunk(200, function ($users) use ($roleIdsByName, $now): void {
                $rows = collect($users)
                    ->map(function ($user) use ($roleIdsByName, $now): ?array {
                        $roleName = trim((string) ($user->role ?? ''));
                        $roleId = $roleIdsByName[$roleName] ?? null;

                        if ($roleName === '' || $roleId === null) {
                            return null;
                        }

                        return [
                            'user_id' => (int) $user->id,
                            'role_id' => (int) $roleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                if ($rows !== []) {
                    DB::table('user_roles')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
    }
};
