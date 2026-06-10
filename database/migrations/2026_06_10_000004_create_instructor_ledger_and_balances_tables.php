<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('settlement_period_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('payout_id')->nullable();
            $table->string('type', 32);
            $table->string('direction', 16);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['instructor_id', 'occurred_at']);
            $table->index('payout_id');
        });

        Schema::create('instructor_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_earned_minor')->default(0);
            $table->unsignedBigInteger('total_paid_minor')->default(0);
            $table->unsignedBigInteger('outstanding_minor')->default(0);
            $table->foreignId('last_ledger_entry_id')->nullable()->constrained('instructor_ledger_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['instructor_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_balances');
        Schema::dropIfExists('instructor_ledger_entries');
    }
};
