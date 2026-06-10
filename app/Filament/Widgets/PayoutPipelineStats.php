<?php

namespace App\Filament\Widgets;

use App\Domain\Revenue\Services\PlatformFinancialMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PayoutPipelineStats extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $metrics = app(PlatformFinancialMetricsService::class)->totals();

        return [
            Stat::make('Pending payouts', (string) $metrics['pending_payouts']),
            Stat::make('Pending confirmation', (string) $metrics['pending_confirmation_payouts']),
            Stat::make('Failed payouts', (string) $metrics['failed_payouts']),
        ];
    }
}
