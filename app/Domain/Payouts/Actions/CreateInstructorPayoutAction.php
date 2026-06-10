<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Payouts\Support\PayoutSnapshot;
use App\Models\InstructorBalance;
use App\Models\Payout;
use App\Models\PayoutBatch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateInstructorPayoutAction
{
    public function execute(PayoutBatch $batch, InstructorBalance $balance): ?Payout
    {
        if ($balance->outstanding_minor <= 0) {
            return null;
        }

        $balanceSnapshotHash = PayoutSnapshot::balanceSnapshotHash($balance);
        $activeSnapshotKey = PayoutSnapshot::activeSnapshotKey(
            $balance->instructor_id,
            $balance->currency,
            $balanceSnapshotHash,
        );

        $existing = Payout::query()
            ->where('active_snapshot_key', $activeSnapshotKey)
            ->first();

        if ($existing !== null) {
            return null;
        }

        try {
            return DB::transaction(function () use ($batch, $balance, $balanceSnapshotHash, $activeSnapshotKey): Payout {
                $lockedBalance = InstructorBalance::query()
                    ->whereKey($balance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedBalance->outstanding_minor <= 0) {
                    return null;
                }

                $payout = Payout::query()->create([
                    'payout_batch_id' => $batch->id,
                    'instructor_id' => $lockedBalance->instructor_id,
                    'amount_minor' => $lockedBalance->outstanding_minor,
                    'currency' => $lockedBalance->currency,
                    'status' => PayoutStatus::Pending,
                    'balance_snapshot_hash' => $balanceSnapshotHash,
                    'active_snapshot_key' => $activeSnapshotKey,
                    'provider_idempotency_key' => 'payout:pending:'.Str::uuid(),
                ]);

                $payout->update([
                    'provider_idempotency_key' => PayoutSnapshot::providerIdempotencyKey($payout->id),
                ]);

                return $payout->fresh();
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateActiveSnapshotKey($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    private function isDuplicateActiveSnapshotKey(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'active_snapshot_key')
            || str_contains($message, 'Duplicate entry');
    }
}
