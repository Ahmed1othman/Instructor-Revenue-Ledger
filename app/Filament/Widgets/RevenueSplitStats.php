<?php

namespace App\Filament\Widgets;

use App\Domain\Revenue\Services\PlatformFinancialMetricsService;
use App\Filament\Widgets\Concerns\FormatsDashboardMoney;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueSplitStats extends BaseWidget
{
    use FormatsDashboardMoney;

    protected static ?int $sort = 21;

    protected function getStats(): array
    {
        $metrics = app(PlatformFinancialMetricsService::class)->totals();
        $currency = $this->dashboardCurrencyContext();
        $format = fn (int $amount): string => $this->formatDashboardAmount($amount, $currency);

        $stats = [
            Stat::make('Platform contractual share', $format($metrics['platform_contractual_share_minor'])),
            Stat::make('Instructor pool', $format($metrics['instructor_pool_minor'])),
            Stat::make('Instructor allocated', $format($metrics['instructor_allocated_minor'])),
            Stat::make('Unallocated instructor pool', $format($metrics['unallocated_instructor_pool_minor'])),
            Stat::make('Total platform retained', $format($metrics['total_platform_retained_minor'])),
        ];

        if ($currency['description'] !== null) {
            $stats[0] = $stats[0]->description($currency['description']);
        }

        return $stats;
    }
}
