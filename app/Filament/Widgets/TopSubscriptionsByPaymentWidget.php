<?php

namespace App\Filament\Widgets;

use App\Domain\Money\Money;
use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Services\SubscriptionFinancialSummaryService;
use App\Models\Subscription;
use Filament\Widgets\Widget;

class TopSubscriptionsByPaymentWidget extends Widget
{
    protected static string $view = 'filament.widgets.analytics-table';

    protected static ?int $sort = 56;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected function getViewData(): array
    {
        $summary = app(SubscriptionFinancialSummaryService::class);

        $subscriptions = Subscription::query()
            ->with(['user', 'plan'])
            ->whereHas('payments', fn ($query) => $query->where('status', PaymentStatus::Succeeded))
            ->get()
            ->sortByDesc(fn (Subscription $subscription): int => $summary->forSubscription($subscription)->paidMinor)
            ->take(5);

        $rows = $subscriptions->map(function (Subscription $subscription) use ($summary): array {
            $financial = $summary->forSubscription($subscription);

            return [
                $subscription->user?->name ?? '—',
                $subscription->plan?->name ?? '—',
                Money::formatMinor($financial->paidMinor, $financial->currency),
            ];
        })->values()->all();

        return [
            'heading' => 'Top subscriptions by payment',
            'columns' => ['Student', 'Plan', 'Payment'],
            'rows' => $rows,
        ];
    }
}
