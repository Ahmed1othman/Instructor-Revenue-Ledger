<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Domain\Revenue\Services\PlatformFinancialMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueSplitStats extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $metrics = app(PlatformFinancialMetricsService::class)->totals();

        return [
            Stat::make('Platform earned', Money::formatMinor($metrics['platform_earned_minor'], 'USD')),
            Stat::make('Instructor allocated', Money::formatMinor($metrics['instructor_allocated_minor'], 'USD')),
            Stat::make('Instructor paid', Money::formatMinor($metrics['instructor_paid_minor'], 'USD')),
            Stat::make('Instructor outstanding', Money::formatMinor($metrics['instructor_outstanding_minor'], 'USD')),
        ];
    }
}
