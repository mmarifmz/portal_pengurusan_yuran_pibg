<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogathon_class_teachers', function (Blueprint $table): void {
            $table->id();
            $table->string('class_name')->unique();
            $table->string('teacher_name')->nullable();
            $table->string('source', 32)->default('manual');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogathon_class_teachers');
    }
};
