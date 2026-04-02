<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_no')->unique();
            $table->string('full_name');
            $table->string('class_name')->nullable();
            $table->string('parent_name')->nullable();
            $table->string('parent_phone')->nullable()->index();
            $table->string('parent_email')->nullable()->index();
            $table->decimal('total_fee', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};