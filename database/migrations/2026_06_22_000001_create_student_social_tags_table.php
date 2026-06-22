<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_social_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('social_tag_id')->constrained('social_tags')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'social_tag_id'], 'student_social_tag_unique');
            $table->index(['social_tag_id', 'student_id'], 'student_social_tags_tag_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_social_tags');
    }
};
