<?php

namespace Database\Factories;

use App\Domain\Revenue\Enums\SettlementPeriodStatus;
use App\Models\SettlementPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettlementPeriod>
 */
class SettlementPeriodFactory extends Factory
{
    protected $model = SettlementPeriod::class;

    public function definition(): array
    {
        $date = now()->startOfMonth();

        return [
            'year' => (int) $date->year,
            'month' => (int) $date->month,
            'period_start' => $date->toDateString(),
            'period_end' => $date->copy()->endOfMonth()->toDateString(),
            'status' => SettlementPeriodStatus::Open,
        ];
    }
}
