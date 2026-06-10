<?php

namespace App\Domain\Revenue\Actions;

use App\Models\Subscription;
use Carbon\Carbon;

class EnsureElapsedDaysAllocatedAction
{
    public function __construct(
        private readonly AllocateRevenueForDayAction $allocateForDay,
    ) {}

    public function execute(Subscription $subscription, Carbon $cancellationDate): void
    {
        $start = $subscription->starts_at->copy()->startOfDay();
        $end = $cancellationDate->copy()->startOfDay();
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $this->allocateForDay->execute($cursor);
            $cursor = $cursor->addDay();
        }
    }
}
