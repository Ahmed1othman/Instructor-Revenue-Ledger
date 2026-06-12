<?php

namespace App\Filament\Widgets;

use App\Domain\Revenue\Services\PlatformFinancialMetricsService;
use App\Filament\Widgets\Concerns\FormatsDashboardMoney;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialOverviewStats extends BaseWidget
{
    use FormatsDashboardMoney;

    protected static ?int $sort = 11;

    protected function getStats(): array
    {
        $metrics = app(PlatformFinancialMetricsService::class)->totals();
        $currency = $this->dashboardCurrencyContext();
        $format = fn (int $amount): string => $this->formatDashboardAmount($amount, $currency);

        $stats = [
            Stat::make('Total student payments', $format($metrics['total_payments_minor'])),
            Stat::make('Earned revenue', $format($metrics['earned_minor'])),
            Stat::make('Unearned revenue liability', $format($metrics['unearned_minor'])),
            Stat::make('Total refunds', $format($metrics['total_refunds_minor'])),
            Stat::make('Remaining refundable liability', $format($metrics['remaining_refundable_minor'])),
        ];

        if ($currency['description'] !== null) {
            $stats[0] = $stats[0]->description($currency['description']);
        }

        return $stats;
    }
}
