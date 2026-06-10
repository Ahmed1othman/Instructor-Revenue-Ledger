<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Payouts\Enums\PayoutBatchStatus;
use App\Models\PayoutBatch;

class CreatePayoutBatchAction
{
    public function execute(): PayoutBatch
    {
        return PayoutBatch::query()->create([
            'status' => PayoutBatchStatus::Pending,
            'initiated_at' => now(),
        ]);
    }
}
