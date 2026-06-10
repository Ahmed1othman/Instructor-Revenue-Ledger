<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Payouts\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

class MarkPayoutPendingConfirmationAction
{
    public function execute(Payout $payout): Payout
    {
        return DB::transaction(function () use ($payout): Payout {
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($locked->status === PayoutStatus::PendingConfirmation) {
                return $locked;
            }

            $locked->update([
                'status' => PayoutStatus::PendingConfirmation,
            ]);

            return $locked->fresh();
        });
    }
}
