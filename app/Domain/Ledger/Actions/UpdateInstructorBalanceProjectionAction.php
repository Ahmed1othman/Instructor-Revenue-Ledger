<?php

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Enums\LedgerDirection;
use App\Models\InstructorBalance;
use App\Models\InstructorLedgerEntry;
use Illuminate\Support\Facades\DB;

class UpdateInstructorBalanceProjectionAction
{
    public function execute(InstructorLedgerEntry $entry): InstructorBalance
    {
        return DB::transaction(function () use ($entry): InstructorBalance {
            $balance = InstructorBalance::query()
                ->where('instructor_id', $entry->instructor_id)
                ->where('currency', $entry->currency)
                ->lockForUpdate()
                ->first();

            if ($balance === null) {
                $balance = InstructorBalance::query()->create([
                    'instructor_id' => $entry->instructor_id,
                    'currency' => $entry->currency,
                    'total_earned_minor' => 0,
                    'total_paid_minor' => 0,
                    'outstanding_minor' => 0,
                ]);

                $balance = InstructorBalance::query()
                    ->whereKey($balance->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($entry->direction === LedgerDirection::Credit) {
                $balance->total_earned_minor += $entry->amount_minor;
                $balance->outstanding_minor += $entry->amount_minor;
            } else {
                $balance->total_paid_minor += $entry->amount_minor;
                $balance->outstanding_minor -= $entry->amount_minor;
            }

            $balance->last_ledger_entry_id = $entry->id;
            $balance->save();

            return $balance->fresh();
        });
    }
}
