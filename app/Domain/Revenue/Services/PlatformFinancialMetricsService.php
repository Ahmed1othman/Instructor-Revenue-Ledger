<?php

namespace App\Domain\Revenue\Services;

use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\RefundStatus;
use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Models\InstructorBalance;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\RevenueAllocation;
use App\Models\Subscription;

class PlatformFinancialMetricsService
{
    public function __construct(
        private readonly SubscriptionFinancialSummaryService $subscriptionSummary,
    ) {}

    /**
     * @return array{
     *     currency: string,
     *     mixed: bool,
     *     label: string,
     *     description: string|null
     * }
     */
    public function dashboardCurrencyContext(): array
    {
        $totalsByCurrency = Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->selectRaw('currency, SUM(amount_minor) as total_minor')
            ->groupBy('currency')
            ->orderByDesc('total_minor')
            ->pluck('total_minor', 'currency');

        if ($totalsByCurrency->isEmpty()) {
            return [
                'currency' => 'USD',
                'mixed' => false,
                'label' => 'USD',
                'description' => null,
            ];
        }

        if ($totalsByCurrency->count() === 1) {
            $currency = (string) $totalsByCurrency->keys()->first();

            return [
                'currency' => $currency,
                'mixed' => false,
                'label' => $currency,
                'description' => null,
            ];
        }

        $currencies = $totalsByCurrency->keys()->sort()->values()->all();

        return [
            'currency' => (string) $totalsByCurrency->keys()->first(),
            'mixed' => true,
            'label' => 'Mixed',
            'description' => sprintf(
                'Mixed currencies (%s) — totals are not FX-converted',
                implode(', ', $currencies),
            ),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function totals(): array
    {
        $totalPaymentsMinor = (int) Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->sum('amount_minor');

        $totalRefundsMinor = (int) Refund::query()
            ->where('status', RefundStatus::Completed)
            ->sum('amount_minor');

        $instructorAllocatedMinor = (int) RevenueAllocation::query()
            ->sum('allocated_amount_minor');

        $earnedMinor = 0;
        $unearnedMinor = 0;
        $remainingRefundableMinor = 0;
        $platformContractualShareMinor = 0;
        $instructorPoolMinor = 0;
        $unallocatedInstructorPoolMinor = 0;
        $totalPlatformRetainedMinor = 0;

        Subscription::query()->each(function (Subscription $subscription) use (
            &$earnedMinor,
            &$unearnedMinor,
            &$remainingRefundableMinor,
            &$platformContractualShareMinor,
            &$instructorPoolMinor,
            &$unallocatedInstructorPoolMinor,
            &$totalPlatformRetainedMinor,
        ): void {
            $summary = $this->subscriptionSummary->forSubscription($subscription);
            $earnedMinor += $summary->earnedMinor;
            $unearnedMinor += $summary->unearnedMinor;
            $remainingRefundableMinor += $summary->remainingRefundableMinor;
            $platformContractualShareMinor += $summary->platformContractualShareMinor;
            $instructorPoolMinor += $summary->instructorPoolMinor;
            $unallocatedInstructorPoolMinor += $summary->unallocatedInstructorPoolMinor;
            $totalPlatformRetainedMinor += $summary->totalPlatformRetainedMinor;
        });

        return [
            'total_payments_minor' => $totalPaymentsMinor,
            'earned_minor' => $earnedMinor,
            'unearned_minor' => $unearnedMinor,
            'total_refunds_minor' => $totalRefundsMinor,
            'remaining_refundable_minor' => $remainingRefundableMinor,
            'platform_contractual_share_minor' => $platformContractualShareMinor,
            'instructor_pool_minor' => $instructorPoolMinor,
            'instructor_allocated_minor' => $instructorAllocatedMinor,
            'unallocated_instructor_pool_minor' => $unallocatedInstructorPoolMinor,
            'total_platform_retained_minor' => $totalPlatformRetainedMinor,
            'instructor_paid_minor' => (int) InstructorBalance::query()->sum('total_paid_minor'),
            'instructor_outstanding_minor' => (int) InstructorBalance::query()->sum('outstanding_minor'),
            'active_subscriptions' => Subscription::query()
                ->where('status', SubscriptionStatus::Active)
                ->count(),
            'expired_subscriptions' => Subscription::query()
                ->where('status', SubscriptionStatus::Expired)
                ->count(),
            'cancelled_subscriptions' => Subscription::query()
                ->where('status', SubscriptionStatus::Cancelled)
                ->count(),
            'refunded_subscriptions' => Subscription::query()
                ->where('status', SubscriptionStatus::Refunded)
                ->count(),
            'pending_payouts' => Payout::query()->where('status', PayoutStatus::Pending)->count(),
            'pending_confirmation_payouts' => Payout::query()
                ->where('status', PayoutStatus::PendingConfirmation)
                ->count(),
            'failed_payouts' => Payout::query()->where('status', PayoutStatus::Failed)->count(),
        ];
    }
}
