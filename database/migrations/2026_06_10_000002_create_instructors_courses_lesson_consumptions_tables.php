<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('lesson_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('valid_watched_seconds');
            $table->dateTime('consumed_at');
            $table->timestamps();

            $table->index(['subscription_id', 'instructor_id', 'consumed_at'], 'lc_sub_instructor_consumed_idx');
            $table->index('consumed_at', 'lc_consumed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_consumptions');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('instructors');
    }
};
