<?php

namespace App\Domain\Payouts\Jobs;

use App\Domain\Payouts\Actions\CheckPayoutStatusAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckPayoutStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $payoutId,
    ) {}

    public function handle(CheckPayoutStatusAction $action): void
    {
        $action->execute($this->payoutId);
    }
}
