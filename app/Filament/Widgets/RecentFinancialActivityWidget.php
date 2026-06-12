<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Domain\Revenue\Services\PlatformFinancialAnalyticsService;
use Filament\Widgets\Widget;

class RecentFinancialActivityWidget extends Widget
{
    protected static string $view = 'filament.widgets.analytics-table';

    protected static ?int $sort = 59;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $rows = app(PlatformFinancialAnalyticsService::class)
            ->recentFinancialActivity()
            ->map(fn (array $event): array => [
                $event['occurred_at']->format('Y-m-d H:i'),
                $event['label'],
                $event['detail'],
                $event['amount_minor'] !== null && $event['currency'] !== null
                    ? Money::formatMinor($event['amount_minor'], $event['currency'])
                    : '—',
            ])
            ->values()
            ->all();

        return [
            'heading' => 'Recent financial activity',
            'description' => 'Refunds, payouts, and daily allocation runs',
            'columns' => ['When', 'Event', 'Detail', 'Amount'],
            'rows' => $rows,
        ];
    }
}
