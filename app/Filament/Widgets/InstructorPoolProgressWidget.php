<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Domain\Revenue\Services\PlatformFinancialMetricsService;
use App\Filament\Widgets\Concerns\LoadsPlatformMetrics;
use Filament\Widgets\Widget;

class InstructorPoolProgressWidget extends Widget
{
    use LoadsPlatformMetrics;

    protected static string $view = 'filament.widgets.segmented-progress';

    protected static ?int $sort = 23;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected function getViewData(): array
    {
        $metrics = $this->platformTotals();
        $currency = app(PlatformFinancialMetricsService::class)->dashboardCurrencyContext();
        $total = max($metrics['instructor_pool_minor'], 1);

        $segments = [
            [
                'label' => 'Allocated to instructors',
                'value' => $metrics['instructor_allocated_minor'],
                'color' => 'bg-emerald-500',
            ],
            [
                'label' => 'Unallocated (no engagement)',
                'value' => $metrics['unallocated_instructor_pool_minor'],
                'color' => 'bg-amber-500',
            ],
        ];

        foreach ($segments as $index => $segment) {
            $segments[$index]['formatted'] = $currency['mixed']
                ? number_format($segment['value'] / 100, 2)
                : Money::formatMinor($segment['value'], $currency['currency']);
            $segments[$index]['percent'] = (int) round(($segment['value'] * 100) / $total);
        }

        return [
            'title' => 'Instructor pool utilization',
            'description' => 'Out of contractual instructor pool from earned revenue',
            'segments' => array_values(array_filter($segments, fn (array $s): bool => $s['value'] > 0)),
        ];
    }
}
