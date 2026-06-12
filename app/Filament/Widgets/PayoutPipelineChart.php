<?php

namespace App\Filament\Widgets;

use App\Domain\Revenue\Services\PlatformFinancialAnalyticsService;
use Filament\Widgets\ChartWidget;

class PayoutPipelineChart extends ChartWidget
{
    protected static ?int $sort = 32;

    protected static ?string $heading = 'Payout pipeline';

    protected static ?string $description = 'Payouts by processing status';

    protected static ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $counts = app(PlatformFinancialAnalyticsService::class)->payoutPipelineCounts();

        return [
            'datasets' => [
                [
                    'label' => 'Payouts',
                    'data' => [
                        $counts['pending'],
                        $counts['pending_confirmation'],
                        $counts['failed'],
                        $counts['succeeded'],
                    ],
                    'backgroundColor' => ['#f59e0b', '#8b5cf6', '#f43f5e', '#10b981'],
                ],
            ],
            'labels' => ['Pending', 'Pending confirmation', 'Failed', 'Succeeded'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
