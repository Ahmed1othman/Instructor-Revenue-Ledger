<?php

namespace App\Console\Commands;

use App\Domain\Revenue\Actions\AllocateRevenueForDayAction;
use App\Domain\Revenue\Actions\AllocateRevenueForSettlementAction;
use App\Domain\Revenue\Enums\SettlementGranularity;
use App\Domain\Revenue\Enums\SettlementPeriodStatus;
use App\Domain\Revenue\Exceptions\AllocationModeConflictException;
use App\Models\SettlementPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RevenueAllocateCommand extends Command
{
    protected $signature = 'revenue:allocate
                            {--date= : Allocate one completed calendar day (YYYY-MM-DD); official path}
                            {--month= : Legacy monthly settlement (YYYY-MM); backward compatibility only}';

    protected $description = 'Allocate instructor earnings by day (official) or by calendar month (legacy)';

    public function handle(
        AllocateRevenueForDayAction $dailyAction,
        AllocateRevenueForSettlementAction $monthlyAction,
    ): int {
        $dateOption = $this->option('date');
        $monthOption = $this->option('month');

        if ($dateOption !== null && $monthOption !== null) {
            $this->error('Provide only one of --date or --month per invocation.');

            return self::FAILURE;
        }

        try {
            if ($monthOption !== null) {
                return $this->allocateMonthly($monthlyAction, $monthOption);
            }

            $date = $dateOption !== null
                ? Carbon::parse((string) $dateOption)->startOfDay()
                : now()->subDay()->startOfDay();

            if ($dateOption !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateOption)) {
                $this->error('Invalid date format. Use YYYY-MM-DD.');

                return self::FAILURE;
            }

            $dailyAction->execute($date);
            $this->info(sprintf('Daily revenue allocation completed for %s.', $date->toDateString()));

            return self::SUCCESS;
        } catch (AllocationModeConflictException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function allocateMonthly(
        AllocateRevenueForSettlementAction $action,
        mixed $month,
    ): int {
        if (! is_string($month) || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Invalid month format. Use YYYY-MM.');

            return self::FAILURE;
        }

        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $date = Carbon::createFromDate($year, $monthNumber, 1);

        $period = SettlementPeriod::query()->firstOrCreate(
            [
                'granularity' => SettlementGranularity::Monthly,
                'period_start' => $date->copy()->startOfMonth()->toDateString(),
            ],
            [
                'year' => $year,
                'month' => $monthNumber,
                'period_end' => $date->copy()->endOfMonth()->toDateString(),
                'status' => SettlementPeriodStatus::Open,
            ],
        );

        $action->execute($period);

        $this->info(sprintf('Revenue allocation completed for %s (legacy monthly).', $month));

        return self::SUCCESS;
    }
}
