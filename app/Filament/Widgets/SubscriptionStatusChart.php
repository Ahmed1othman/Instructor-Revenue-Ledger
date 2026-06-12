<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\LoadsPlatformMetrics;
use Filament\Widgets\ChartWidget;

class SubscriptionStatusChart extends ChartWidget
{
    use LoadsPlatformMetrics;

    protected static ?int $sort = 42;

    protected static ?string $heading = 'Subscription mix';

    protected static ?string $description = 'Lifecycle status distribution';

    protected static ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $metrics = $this->platformTotals();

        return [
            'datasets' => [
                [
                    'data' => [
                        $metrics['active_subscriptions'],
                        $metrics['expired_subscriptions'],
                        $metrics['cancelled_subscriptions'],
                        $metrics['refunded_subscriptions'],
                    ],
                    'backgroundColor' => ['#10b981', '#64748b', '#f59e0b', '#f43f5e'],
                ],
            ],
            'labels' => ['Active', 'Expired', 'Cancelled', 'Refunded'],
        ];
    }
}
