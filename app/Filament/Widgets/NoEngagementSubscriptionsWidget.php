<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Domain\Revenue\Services\PlatformFinancialAnalyticsService;
use Filament\Widgets\Widget;

class NoEngagementSubscriptionsWidget extends Widget
{
    protected static string $view = 'filament.widgets.analytics-table';

    protected static ?int $sort = 58;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $rows = app(PlatformFinancialAnalyticsService::class)
            ->subscriptionsWithEarnedButNoInstructorAllocation()
            ->map(fn (array $row): array => [
                $row['subscription']->user?->name ?? '—',
                Money::formatMinor($row['summary']->earnedMinor, $row['summary']->currency),
                Money::formatMinor($row['summary']->unallocatedInstructorPoolMinor, $row['summary']->currency),
            ])
            ->values()
            ->all();

        return [
            'heading' => 'Earned with unallocated instructor pool (no engagement)',
            'description' => 'Subscriptions where earned revenue exists but instructors received no allocation',
            'columns' => ['Student', 'Earned', 'Unallocated pool'],
            'rows' => $rows,
        ];
    }
}
