<?php

namespace App\Console\Commands;

use App\Domain\Revenue\Actions\AllocateRevenueForSettlementAction;
use App\Domain\Revenue\Enums\SettlementPeriodStatus;
use App\Models\SettlementPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;
class RevenueAllocateCommand extends Command
{
    protected $signature = 'revenue:allocate {--month= : Settlement month as YYYY-MM (defaults to previous calendar month)}';

    protected $description = 'Allocate instructor earnings for a calendar-month settlement period';

    public function handle(AllocateRevenueForSettlementAction $action): int
    {
        $month = $this->option('month') ?? now()->subMonth()->format('Y-m');

        if (! is_string($month) || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Invalid month format. Use YYYY-MM.');

            return self::FAILURE;
        }

        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $date = Carbon::createFromDate($year, $monthNumber, 1);

        $period = SettlementPeriod::query()->firstOrCreate(
            ['year' => $year, 'month' => $monthNumber],
            [
                'period_start' => $date->copy()->startOfMonth()->toDateString(),
                'period_end' => $date->copy()->endOfMonth()->toDateString(),
                'status' => SettlementPeriodStatus::Open,
            ],
        );

        $action->execute($period);

        $this->info(sprintf('Revenue allocation completed for %s.', $month));

        return self::SUCCESS;
    }
}
