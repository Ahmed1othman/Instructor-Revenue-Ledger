<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\LoadsPlatformMetrics;
use Filament\Widgets\ChartWidget;

class RevenueCompositionChart extends ChartWidget
{
    use LoadsPlatformMetrics;

    protected static ?int $sort = 12;

    protected static ?string $heading = 'Payment composition';

    protected static ?string $description = 'How student payments split between earned, unearned liability, and refunds';

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
                        $metrics['earned_minor'],
                        $metrics['unearned_minor'],
                        $metrics['total_refunds_minor'],
                    ],
                    'backgroundColor' => ['#10b981', '#0ea5e9', '#f43f5e'],
                ],
            ],
            'labels' => ['Earned revenue', 'Unearned liability', 'Refunded'],
        ];
    }
}
