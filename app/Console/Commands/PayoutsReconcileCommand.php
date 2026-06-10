<?php

namespace App\Console\Commands;

use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Payouts\Jobs\CheckPayoutStatusJob;
use App\Models\Payout;
use Illuminate\Console\Command;

class PayoutsReconcileCommand extends Command
{
    protected $signature = 'payouts:reconcile';

    protected $description = 'Dispatch status checks for payouts awaiting provider confirmation';

    public function handle(): int
    {
        $payouts = Payout::query()
            ->where('status', PayoutStatus::PendingConfirmation)
            ->get();

        foreach ($payouts as $payout) {
            CheckPayoutStatusJob::dispatch($payout->id);
        }

        $this->info(sprintf('Dispatched status checks for %d payout(s).', $payouts->count()));

        return self::SUCCESS;
    }
}
