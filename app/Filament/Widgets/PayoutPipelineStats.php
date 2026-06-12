<?php

namespace App\Filament\Widgets;

use App\Domain\Revenue\Services\PlatformFinancialMetricsService;
use App\Filament\Widgets\Concerns\FormatsDashboardMoney;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PayoutPipelineStats extends BaseWidget
{
    use FormatsDashboardMoney;

    protected static ?int $sort = 31;

    protected function getStats(): array
    {
        $metrics = app(PlatformFinancialMetricsService::class)->totals();
        $currency = $this->dashboardCurrencyContext();
        $format = fn (int $amount): string => $this->formatDashboardAmount($amount, $currency);

        $stats = [
            Stat::make('Instructor paid', $format($metrics['instructor_paid_minor'])),
            Stat::make('Instructor outstanding', $format($metrics['instructor_outstanding_minor'])),
            Stat::make('Pending payouts', (string) $metrics['pending_payouts']),
            Stat::make('Pending confirmation', (string) $metrics['pending_confirmation_payouts']),
            Stat::make('Failed payouts', (string) $metrics['failed_payouts']),
        ];

        if ($currency['description'] !== null) {
            $stats[0] = $stats[0]->description($currency['description']);
        }

        return $stats;
    }
}
