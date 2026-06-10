<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_periods', function (Blueprint $table) {
            $table->string('granularity', 16)->default('monthly')->after('id');
        });

        DB::table('settlement_periods')->update(['granularity' => 'monthly']);

        Schema::table('settlement_periods', function (Blueprint $table) {
            $table->dropUnique(['year', 'month']);
            $table->unique(['granularity', 'period_start'], 'sp_granularity_period_start_uniq');
        });

        Schema::table('revenue_allocations', function (Blueprint $table) {
            $table->date('allocation_date')->nullable()->after('settlement_period_id');
            $table->index(['allocation_date', 'subscription_id'], 'ra_allocation_date_subscription_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('cancelled_at')->nullable()->after('ends_at');
            $table->dateTime('refunded_at')->nullable()->after('cancelled_at');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->date('cancellation_date');
            $table->date('refund_starts_on');
            $table->unsignedInteger('used_days');
            $table->unsignedInteger('unused_days');
            $table->string('status', 32);
            $table->string('reason')->nullable();
            $table->string('idempotency_key')->unique();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index('subscription_id');
            $table->index('student_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'refunded_at']);
        });

        Schema::table('revenue_allocations', function (Blueprint $table) {
            $table->dropIndex('ra_allocation_date_subscription_idx');
            $table->dropColumn('allocation_date');
        });

        Schema::table('settlement_periods', function (Blueprint $table) {
            $table->dropUnique('sp_granularity_period_start_uniq');
            $table->unique(['year', 'month']);
            $table->dropColumn('granularity');
        });
    }
};
