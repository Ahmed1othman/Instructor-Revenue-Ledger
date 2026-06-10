<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Domain\Revenue\Services\PlatformFinancialMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialOverviewStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $metrics = app(PlatformFinancialMetricsService::class)->totals();

        return [
            Stat::make('Total payments', Money::formatMinor($metrics['total_payments_minor'], 'USD')),
            Stat::make('Earned revenue', Money::formatMinor($metrics['earned_minor'], 'USD')),
            Stat::make('Unearned liability', Money::formatMinor($metrics['unearned_minor'], 'USD')),
            Stat::make('Total refunds', Money::formatMinor($metrics['total_refunds_minor'], 'USD')),
            Stat::make('Remaining refundable', Money::formatMinor($metrics['remaining_refundable_minor'], 'USD')),
        ];
    }
}
