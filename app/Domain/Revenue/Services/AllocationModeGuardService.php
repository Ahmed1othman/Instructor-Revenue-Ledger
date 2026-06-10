<?php

namespace App\Domain\Revenue\Services;

use App\Domain\Revenue\Enums\SettlementGranularity;
use App\Domain\Revenue\Exceptions\AllocationModeConflictException;
use App\Models\RevenueAllocation;
use Carbon\Carbon;

class AllocationModeGuardService
{
    public function assertDailyAllocationAllowed(Carbon $date): void
    {
        $year = (int) $date->year;
        $month = (int) $date->month;
        $monthLabel = sprintf('%04d-%02d', $year, $month);

        $hasMonthlyAllocations = RevenueAllocation::query()
            ->whereHas('settlementPeriod', function ($query) use ($year, $month): void {
                $query->where('granularity', SettlementGranularity::Monthly)
                    ->where('year', $year)
                    ->where('month', $month);
            })
            ->exists();

        if ($hasMonthlyAllocations) {
            throw new AllocationModeConflictException(
                sprintf(
                    'Cannot run daily allocation for %s because monthly allocation already exists for %s.',
                    $date->toDateString(),
                    $monthLabel,
                ),
            );
        }
    }

    public function assertMonthlyAllocationAllowed(int $year, int $month): void
    {
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthLabel = sprintf('%04d-%02d', $year, $month);

        $hasDailyAllocations = RevenueAllocation::query()
            ->whereHas('settlementPeriod', function ($query) use ($monthStart, $monthEnd): void {
                $query->where('granularity', SettlementGranularity::Daily)
                    ->whereBetween('period_start', [
                        $monthStart->toDateString(),
                        $monthEnd->toDateString(),
                    ]);
            })
            ->exists();

        if ($hasDailyAllocations) {
            throw new AllocationModeConflictException(
                sprintf(
                    'Cannot run monthly allocation for %s because daily allocations already exist for this month.',
                    $monthLabel,
                ),
            );
        }
    }
}
