<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Domain\Revenue\Services\PlatformFinancialAnalyticsService;
use Filament\Widgets\Widget;

class TopSubscriptionsByRefundableWidget extends Widget
{
    protected static string $view = 'filament.widgets.analytics-table';

    protected static ?int $sort = 57;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected function getViewData(): array
    {
        $rows = app(PlatformFinancialAnalyticsService::class)
            ->topSubscriptionsByRemainingRefundable()
            ->map(fn (array $row): array => [
                $row['subscription']->user?->name ?? '—',
                $row['subscription']->status->value,
                Money::formatMinor($row['summary']->remainingRefundableMinor, $row['summary']->currency),
            ])
            ->values()
            ->all();

        return [
            'heading' => 'Highest remaining refundable',
            'columns' => ['Student', 'Status', 'Remaining refundable'],
            'rows' => $rows,
        ];
    }
}
