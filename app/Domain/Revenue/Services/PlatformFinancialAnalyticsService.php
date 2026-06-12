<?php

namespace App\Domain\Revenue\Services;

use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Revenue\DTOs\SubscriptionFinancialSummary;
use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\RefundStatus;
use App\Domain\Revenue\Enums\SettlementGranularity;
use App\Domain\Revenue\Enums\SettlementPeriodStatus;
use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\SettlementPeriod;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlatformFinancialAnalyticsService
{
    public function __construct(
        private readonly RevenueRecognitionService $recognitionService,
        private readonly SubscriptionFinancialSummaryService $subscriptionSummary,
    ) {}

    /**
     * @return array{labels: array<int, string>, earned: array<int, int>, refunds: array<int, int>}
     */
    public function monthlyFinancialTrend(int $months = 6): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();
        $labels = [];
        $earnedByMonth = [];
        $refundsByMonth = [];

        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo(now()->startOfMonth())) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $earnedByMonth[$key] = 0;
            $refundsByMonth[$key] = 0;
            $cursor->addMonth();
        }

        SettlementPeriod::query()
            ->where('granularity', SettlementGranularity::Daily)
            ->where('status', SettlementPeriodStatus::Allocated)
            ->where('period_start', '>=', $start->toDateString())
            ->orderBy('period_start')
            ->each(function (SettlementPeriod $period) use (&$earnedByMonth): void {
                $day = Carbon::parse($period->period_start)->startOfDay();
                $monthKey = $day->format('Y-m');

                if (! array_key_exists($monthKey, $earnedByMonth)) {
                    return;
                }

                Payment::query()
                    ->with(['subscription.plan'])
                    ->where('status', PaymentStatus::Succeeded)
                    ->whereHas('subscription', function ($query) use ($day): void {
                        $query->where('starts_at', '<=', $day->copy()->endOfDay())
                            ->where('ends_at', '>=', $day->copy()->startOfDay());
                    })
                    ->each(function (Payment $payment) use ($day, &$earnedByMonth, $monthKey): void {
                        $earnedByMonth[$monthKey] += $this->recognitionService->earnedAmountMinorForDay(
                            $payment,
                            $payment->subscription,
                            $day,
                        );
                    });
            });

        Refund::query()
            ->where('status', RefundStatus::Completed)
            ->where('processed_at', '>=', $start)
            ->get()
            ->each(function (Refund $refund) use (&$refundsByMonth): void {
                $monthKey = $refund->processed_at->format('Y-m');

                if (! array_key_exists($monthKey, $refundsByMonth)) {
                    return;
                }

                $refundsByMonth[$monthKey] += $refund->amount_minor;
            });

        return [
            'labels' => $labels,
            'earned' => array_values($earnedByMonth),
            'refunds' => array_values($refundsByMonth),
        ];
    }

    /**
     * @return Collection<int, array{subscription: Subscription, summary: SubscriptionFinancialSummary}>
     */
    public function topSubscriptionsByPayment(int $limit = 5): Collection
    {
        return Subscription::query()
            ->with(['user', 'plan'])
            ->get()
            ->map(fn (Subscription $subscription): array => [
                'subscription' => $subscription,
                'summary' => $this->subscriptionSummary->forSubscription($subscription),
            ])
            ->sortByDesc(fn (array $row): int => $row['summary']->paidMinor)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array{subscription: Subscription, summary: SubscriptionFinancialSummary}>
     */
    public function topSubscriptionsByRemainingRefundable(int $limit = 5): Collection
    {
        return Subscription::query()
            ->with(['user', 'plan'])
            ->where('status', '!=', SubscriptionStatus::Refunded)
            ->get()
            ->map(fn (Subscription $subscription): array => [
                'subscription' => $subscription,
                'summary' => $this->subscriptionSummary->forSubscription($subscription),
            ])
            ->filter(fn (array $row): bool => $row['summary']->remainingRefundableMinor > 0)
            ->sortByDesc(fn (array $row): int => $row['summary']->remainingRefundableMinor)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array{subscription: Subscription, summary: SubscriptionFinancialSummary}>
     */
    public function subscriptionsWithEarnedButNoInstructorAllocation(int $limit = 5): Collection
    {
        return Subscription::query()
            ->with(['user', 'plan'])
            ->get()
            ->map(fn (Subscription $subscription): array => [
                'subscription' => $subscription,
                'summary' => $this->subscriptionSummary->forSubscription($subscription),
            ])
            ->filter(function (array $row): bool {
                $summary = $row['summary'];

                return $summary->earnedMinor > 0
                    && $summary->instructorPoolAllocatedMinor === 0
                    && $summary->unallocatedInstructorPoolMinor > 0;
            })
            ->sortByDesc(fn (array $row): int => $row['summary']->unallocatedInstructorPoolMinor)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     occurred_at: Carbon,
     *     type: string,
     *     label: string,
     *     detail: string,
     *     amount_minor: int|null,
     *     currency: string|null
     * }>
     */
    public function recentFinancialActivity(int $limit = 10): Collection
    {
        $events = collect();

        Refund::query()
            ->with(['student', 'subscription.user'])
            ->where('status', RefundStatus::Completed)
            ->orderByDesc('processed_at')
            ->limit($limit)
            ->get()
            ->each(function (Refund $refund) use ($events): void {
                $events->push([
                    'occurred_at' => $refund->processed_at,
                    'type' => 'refund',
                    'label' => 'Refund completed',
                    'detail' => $refund->subscription?->user?->name ?? 'Subscription #'.$refund->subscription_id,
                    'amount_minor' => $refund->amount_minor,
                    'currency' => $refund->currency,
                ]);
            });

        Payout::query()
            ->with('instructor')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->each(function (Payout $payout) use ($events): void {
                $events->push([
                    'occurred_at' => $payout->updated_at,
                    'type' => 'payout',
                    'label' => 'Payout '.$payout->status->value,
                    'detail' => $payout->instructor?->name ?? 'Instructor #'.$payout->instructor_id,
                    'amount_minor' => $payout->amount_minor,
                    'currency' => $payout->currency,
                ]);
            });

        SettlementPeriod::query()
            ->where('granularity', SettlementGranularity::Daily)
            ->where('status', SettlementPeriodStatus::Allocated)
            ->orderByDesc('period_start')
            ->limit($limit)
            ->get()
            ->each(function (SettlementPeriod $period) use ($events): void {
                $events->push([
                    'occurred_at' => Carbon::parse($period->period_start)->endOfDay(),
                    'type' => 'allocation',
                    'label' => 'Daily allocation',
                    'detail' => $period->period_start,
                    'amount_minor' => null,
                    'currency' => null,
                ]);
            });

        return $events
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->values();
    }

    /**
     * @return array<string, int>
     */
    public function payoutPipelineCounts(): array
    {
        return [
            'pending' => Payout::query()->where('status', PayoutStatus::Pending)->count(),
            'pending_confirmation' => Payout::query()->where('status', PayoutStatus::PendingConfirmation)->count(),
            'failed' => Payout::query()->where('status', PayoutStatus::Failed)->count(),
            'succeeded' => Payout::query()->where('status', PayoutStatus::Succeeded)->count(),
        ];
    }
}
