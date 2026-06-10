<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->unsignedSmallInteger('instructor_share_bps')->default(6000);
            $table->unsignedInteger('duration_days');
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->char('currency', 3);
            $table->timestamps();

            $table->index('status');
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->dateTime('paid_at');
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
