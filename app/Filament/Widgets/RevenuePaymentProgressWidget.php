<?php

namespace App\Filament\Widgets;

use App\Filament\Support\FinancialSegmentBuilder;
use App\Filament\Widgets\Concerns\LoadsPlatformMetrics;
use Filament\Widgets\Widget;

class RevenuePaymentProgressWidget extends Widget
{
    use LoadsPlatformMetrics;

    protected static string $view = 'filament.widgets.segmented-progress';

    protected static ?int $sort = 13;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected function getViewData(): array
    {
        $metrics = $this->platformTotals();
        $total = max($metrics['total_payments_minor'], 1);

        $segments = FinancialSegmentBuilder::withPercents([
            ['label' => 'Earned', 'value' => $metrics['earned_minor'], 'color' => 'bg-emerald-500', 'formatted' => ''],
            ['label' => 'Unearned', 'value' => $metrics['unearned_minor'], 'color' => 'bg-sky-500', 'formatted' => ''],
            ['label' => 'Refunded', 'value' => $metrics['total_refunds_minor'], 'color' => 'bg-rose-500', 'formatted' => ''],
        ], $total);

        $currency = app(\App\Domain\Revenue\Services\PlatformFinancialMetricsService::class)
            ->dashboardCurrencyContext();

        foreach ($segments as $index => $segment) {
            if (! $currency['mixed']) {
                $segments[$index]['formatted'] = \App\Domain\Money\Money::formatMinor(
                    $segment['value'],
                    $currency['currency'],
                );
            } else {
                $segments[$index]['formatted'] = number_format($segment['value'] / 100, 2);
            }
        }

        return [
            'title' => 'Payment utilization',
            'description' => 'Share of total student payments',
            'segments' => array_values(array_filter($segments, fn (array $s): bool => $s['value'] > 0)),
        ];
    }
}
