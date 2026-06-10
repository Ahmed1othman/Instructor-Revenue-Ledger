<?php

namespace App\Filament\Widgets;

use App\Domain\Revenue\Services\PlatformFinancialMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionStatusStats extends BaseWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $metrics = app(PlatformFinancialMetricsService::class)->totals();

        return [
            Stat::make('Active subscriptions', (string) $metrics['active_subscriptions']),
            Stat::make('Cancelled / refunded', (string) $metrics['cancelled_refunded_subscriptions']),
        ];
    }
}
