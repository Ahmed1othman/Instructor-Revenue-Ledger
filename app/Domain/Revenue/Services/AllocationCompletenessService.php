<?php

namespace App\Domain\Revenue\Services;

use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\SettlementGranularity;
use App\Models\Payment;
use App\Models\RevenueAllocation;
use App\Models\SettlementPeriod;
use Carbon\Carbon;

class AllocationCompletenessService
{
    /**
     * @return list<string> ISO dates (YYYY-MM-DD) with no daily allocation in the month
     */
    public function unallocatedElapsedDaysInMonth(int $year, int $month): array
    {
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $lastAllocatableDay = now()->startOfDay()->subDay();

        if ($lastAllocatableDay->lessThan($monthStart)) {
            return [];
        }

        $rangeEnd = $lastAllocatableDay->lessThan($monthEnd) ? $lastAllocatableDay : $monthEnd;

        $hasMonthlyAllocations = RevenueAllocation::query()
            ->whereHas('settlementPeriod', function ($query) use ($year, $month): void {
                $query->where('granularity', SettlementGranularity::Monthly)
                    ->where('year', $year)
                    ->where('month', $month);
            })
            ->exists();

        if ($hasMonthlyAllocations) {
            return [];
        }

        $allocatedDates = SettlementPeriod::query()
            ->daily()
            ->whereBetween('period_start', [$monthStart->toDateString(), $rangeEnd->toDateString()])
            ->whereHas('revenueAllocations')
            ->pluck('period_start')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();

        $allocatedLookup = array_fill_keys($allocatedDates, true);
        $missing = [];
        $cursor = $monthStart->copy();

        while ($cursor->lessThanOrEqualTo($rangeEnd)) {
            $dateString = $cursor->toDateString();

            if (! isset($allocatedLookup[$dateString]) && $this->hasAllocatableSubscriptionsOnDate($cursor)) {
                $missing[] = $dateString;
            }

            $cursor = $cursor->addDay();
        }

        return $missing;
    }

    private function hasAllocatableSubscriptionsOnDate(Carbon $date): bool
    {
        return Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->whereHas('subscription', function ($query) use ($date): void {
                $query->where('starts_at', '<=', $date->copy()->endOfDay())
                    ->where('ends_at', '>=', $date->copy()->startOfDay());
            })
            ->exists();
    }
}
