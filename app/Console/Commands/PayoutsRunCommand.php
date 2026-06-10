<?php

namespace App\Console\Commands;

use App\Domain\Payouts\Actions\CreateInstructorPayoutAction;
use App\Domain\Payouts\Actions\CreatePayoutBatchAction;
use App\Domain\Payouts\Jobs\ProcessInstructorPayoutJob;
use App\Models\InstructorBalance;
use Illuminate\Console\Command;

class PayoutsRunCommand extends Command
{
    protected $signature = 'payouts:run';

    protected $description = 'Create payout batch and dispatch jobs for instructors with outstanding balances';

    public function handle(
        CreatePayoutBatchAction $createBatch,
        CreateInstructorPayoutAction $createPayout,
    ): int {
        $batch = $createBatch->execute();
        $created = 0;

        $balances = InstructorBalance::query()
            ->where('outstanding_minor', '>', 0)
            ->get();

        foreach ($balances as $balance) {
            $payout = $createPayout->execute($batch, $balance);

            if ($payout === null) {
                continue;
            }

            ProcessInstructorPayoutJob::dispatch($payout->id);
            $created++;
        }

        $this->info(sprintf(
            'Payout batch #%d created with %d payout(s).',
            $batch->id,
            $created,
        ));

        return self::SUCCESS;
    }
}
