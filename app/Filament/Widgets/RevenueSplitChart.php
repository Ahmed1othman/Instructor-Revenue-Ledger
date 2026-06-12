<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\LoadsPlatformMetrics;
use Filament\Widgets\ChartWidget;

class RevenueSplitChart extends ChartWidget
{
    use LoadsPlatformMetrics;

    protected static ?int $sort = 22;

    protected static ?string $heading = 'Earned revenue split';

    protected static ?string $description = 'Platform share vs instructor pool utilization';

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
                        $metrics['platform_contractual_share_minor'],
                        $metrics['instructor_allocated_minor'],
                        $metrics['unallocated_instructor_pool_minor'],
                    ],
                    'backgroundColor' => ['#6366f1', '#10b981', '#f59e0b'],
                ],
            ],
            'labels' => [
                'Platform contractual share',
                'Instructor allocated',
                'Unallocated instructor pool',
            ],
        ];
    }
}
