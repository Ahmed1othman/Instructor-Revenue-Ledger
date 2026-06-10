<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 32);
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('revenue_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('instructor_pool_minor');
            $table->unsignedInteger('engagement_weight');
            $table->unsignedBigInteger('allocated_amount_minor');
            $table->char('currency', 3);
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['settlement_period_id', 'subscription_id']);
            $table->index('instructor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_allocations');
        Schema::dropIfExists('settlement_periods');
    }
};
