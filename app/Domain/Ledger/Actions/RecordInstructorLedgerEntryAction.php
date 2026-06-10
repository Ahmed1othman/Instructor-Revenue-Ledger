<?php

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Models\InstructorLedgerEntry;
use Carbon\CarbonInterface;

class RecordInstructorLedgerEntryAction
{
    public function execute(
        int $instructorId,
        LedgerEntryType $type,
        LedgerDirection $direction,
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        ?int $subscriptionId = null,
        ?int $settlementPeriodId = null,
        ?int $payoutId = null,
        ?array $metadata = null,
        ?CarbonInterface $occurredAt = null,
    ): InstructorLedgerEntry {
        $existing = InstructorLedgerEntry::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return InstructorLedgerEntry::query()->create([
            'instructor_id' => $instructorId,
            'subscription_id' => $subscriptionId,
            'settlement_period_id' => $settlementPeriodId,
            'payout_id' => $payoutId,
            'type' => $type,
            'direction' => $direction,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'idempotency_key' => $idempotencyKey,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}
