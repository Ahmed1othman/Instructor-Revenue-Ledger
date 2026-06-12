<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Domain\Revenue\Services\PlatformFinancialMetricsService;
use App\Filament\Widgets\Concerns\LoadsPlatformMetrics;
use Filament\Widgets\Widget;

class PayoutSettlementProgressWidget extends Widget
{
    use LoadsPlatformMetrics;

    protected static string $view = 'filament.widgets.segmented-progress';

    protected static ?int $sort = 33;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected function getViewData(): array
    {
        $metrics = $this->platformTotals();
        $currency = app(PlatformFinancialMetricsService::class)->dashboardCurrencyContext();
        $total = max($metrics['instructor_allocated_minor'], 1);

        $segments = [
            [
                'label' => 'Paid to instructors',
                'value' => $metrics['instructor_paid_minor'],
                'color' => 'bg-emerald-500',
            ],
            [
                'label' => 'Outstanding',
                'value' => $metrics['instructor_outstanding_minor'],
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
            'title' => 'Instructor settlement',
            'description' => 'Paid vs outstanding out of allocated instructor earnings',
            'segments' => array_values(array_filter($segments, fn (array $s): bool => $s['value'] > 0)),
        ];
    }
}
