<?php

namespace App\Console\Commands;

use App\Domain\Payouts\Actions\CreateInstructorPayoutAction;
use App\Domain\Payouts\Actions\CreatePayoutBatchAction;
use App\Domain\Payouts\Jobs\ProcessInstructorPayoutJob;
use App\Domain\Revenue\Services\AllocationCompletenessService;
use App\Models\InstructorBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pays instructors with outstanding_minor > 0 only.
 *
 * Outstanding balances come exclusively from prior earning_credit ledger entries
 * written during revenue allocation — future unallocated days never increase outstanding.
 *
 * In the official daily-allocation lifecycle, elapsed days in the payout target period
 * should be fully allocated before monthly payout runs. Provider success is the only
 * event that moves outstanding to paid (payout_debit); timeouts and failures do not.
 */
class PayoutsRunCommand extends Command
{
    protected $signature = 'payouts:run';

    protected $description = 'Create payout batch and dispatch jobs for instructors with outstanding balances';

    public function handle(
        CreatePayoutBatchAction $createBatch,
        CreateInstructorPayoutAction $createPayout,
        AllocationCompletenessService $allocationCompleteness,
    ): int {
        $this->warnIfAllocationIncomplete($allocationCompleteness);

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

    private function warnIfAllocationIncomplete(AllocationCompletenessService $allocationCompleteness): void
    {
        $previousMonth = now()->subMonth();
        $missingDays = $allocationCompleteness->unallocatedElapsedDaysInMonth(
            (int) $previousMonth->year,
            (int) $previousMonth->month,
        );

        if ($missingDays === []) {
            return;
        }

        $message = sprintf(
            'Allocation completeness warning: %d unallocated elapsed day(s) in %s before payout run: %s',
            count($missingDays),
            $previousMonth->format('Y-m'),
            implode(', ', array_slice($missingDays, 0, 5)).(count($missingDays) > 5 ? '…' : ''),
        );

        Log::warning($message);
        $this->warn($message);
    }
}
