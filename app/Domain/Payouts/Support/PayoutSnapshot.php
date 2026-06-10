<?php

namespace App\Domain\Payouts\Support;

use App\Models\InstructorBalance;

final class PayoutSnapshot
{
    public static function balanceSnapshotHash(InstructorBalance $balance): string
    {
        $lastLedgerEntryId = $balance->last_ledger_entry_id ?? 'null';

        return hash('sha256', sprintf(
            '%d:%s:%d:%s',
            $balance->instructor_id,
            $balance->currency,
            $balance->outstanding_minor,
            $lastLedgerEntryId,
        ));
    }

    public static function activeSnapshotKey(
        int $instructorId,
        string $currency,
        string $balanceSnapshotHash,
    ): string {
        return sprintf('%d:%s:%s', $instructorId, $currency, $balanceSnapshotHash);
    }

    public static function providerIdempotencyKey(int $payoutId): string
    {
        return 'payout:'.$payoutId;
    }

    public static function ledgerDebitIdempotencyKey(int $payoutId): string
    {
        return 'ledger:payout_debit:'.$payoutId;
    }
}
