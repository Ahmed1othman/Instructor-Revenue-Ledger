<?php

namespace App\Filament\Widgets;

use App\Domain\Revenue\Services\PlatformFinancialAnalyticsService;
use Filament\Widgets\ChartWidget;

class MonthlyFinancialTrendChart extends ChartWidget
{
    protected static ?int $sort = 53;

    protected static ?string $heading = 'Monthly financial trend';

    protected static ?string $description = 'Earned revenue vs refunds by month (integer minor units)';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trend = app(PlatformFinancialAnalyticsService::class)->monthlyFinancialTrend();

        return [
            'datasets' => [
                [
                    'label' => 'Earned revenue',
                    'data' => $trend['earned'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                ],
                [
                    'label' => 'Refunds',
                    'data' => $trend['refunds'],
                    'borderColor' => '#f43f5e',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }
}
