<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Ledger\Actions\RecordInstructorLedgerEntryAction;
use App\Domain\Ledger\Actions\UpdateInstructorBalanceProjectionAction;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Payouts\DTOs\PayoutProviderResult;
use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Payouts\Support\PayoutSnapshot;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

class MarkPayoutSucceededAction
{
    public function __construct(
        private readonly RecordInstructorLedgerEntryAction $recordLedgerEntry,
        private readonly UpdateInstructorBalanceProjectionAction $updateBalance,
    ) {}

    public function execute(Payout $payout, ?PayoutProviderResult $result = null): Payout
    {
        return DB::transaction(function () use ($payout, $result): Payout {
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($locked->status === PayoutStatus::Succeeded) {
                return $locked;
            }

            $ledgerKey = PayoutSnapshot::ledgerDebitIdempotencyKey($locked->id);

            $entry = $this->recordLedgerEntry->execute(
                instructorId: $locked->instructor_id,
                type: LedgerEntryType::PayoutDebit,
                direction: LedgerDirection::Debit,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                idempotencyKey: $ledgerKey,
                payoutId: $locked->id,
                metadata: $result ? [
                    'provider_reference' => $result->providerReference,
                    'message' => $result->message,
                ] : null,
            );

            if ($entry->wasRecentlyCreated) {
                $this->updateBalance->execute($entry);
            }

            $locked->update([
                'status' => PayoutStatus::Succeeded,
                'active_snapshot_key' => null,
            ]);

            return $locked->fresh();
        });
    }
}
