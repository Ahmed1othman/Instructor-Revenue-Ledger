<?php

namespace App\Domain\Payouts\Jobs;

use App\Domain\Payouts\Actions\ProcessInstructorPayoutAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessInstructorPayoutJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $payoutId,
    ) {}

    public function handle(ProcessInstructorPayoutAction $action): void
    {
        $action->execute($this->payoutId);
    }
}
