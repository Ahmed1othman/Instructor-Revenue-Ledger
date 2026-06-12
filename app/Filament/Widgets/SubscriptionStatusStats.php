<?php

namespace App\Filament\Widgets;

use App\Domain\Revenue\Services\PlatformFinancialMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionStatusStats extends BaseWidget
{
    protected static ?int $sort = 41;

    protected function getStats(): array
    {
        $metrics = app(PlatformFinancialMetricsService::class)->totals();

        return [
            Stat::make('Active', (string) $metrics['active_subscriptions']),
            Stat::make('Expired', (string) $metrics['expired_subscriptions']),
            Stat::make('Cancelled', (string) $metrics['cancelled_subscriptions']),
            Stat::make('Refunded', (string) $metrics['refunded_subscriptions']),
        ];
    }
}
