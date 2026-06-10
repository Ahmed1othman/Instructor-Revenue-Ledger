<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32);
            $table->dateTime('initiated_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->string('balance_snapshot_hash', 64);
            $table->string('active_snapshot_key')->nullable()->unique();
            $table->string('provider_idempotency_key')->unique();
            $table->timestamps();

            $table->index(['instructor_id', 'status']);
            $table->index('balance_snapshot_hash');
        });

        Schema::create('payout_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('status', 32);
            $table->string('provider_result', 32)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key');
            $table->dateTime('attempted_at');
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['payout_id', 'type']);
            $table->index('idempotency_key');
        });

        Schema::table('instructor_ledger_entries', function (Blueprint $table) {
            $table->foreign('payout_id')->references('id')->on('payouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('instructor_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['payout_id']);
        });

        Schema::dropIfExists('payout_attempts');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payout_batches');
    }
};
