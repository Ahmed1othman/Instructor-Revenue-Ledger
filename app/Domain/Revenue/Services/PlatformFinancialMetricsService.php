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
        $platformEarnedMinor = 0;

        Subscription::query()->each(function (Subscription $subscription) use (
            &$earnedMinor,
            &$unearnedMinor,
            &$remainingRefundableMinor,
            &$platformEarnedMinor,
        ): void {
            $summary = $this->subscriptionSummary->forSubscription($subscription);
            $earnedMinor += $summary->earnedMinor;
            $unearnedMinor += $summary->unearnedMinor;
            $remainingRefundableMinor += $summary->remainingRefundableMinor;
            $platformEarnedMinor += $summary->platformEarnedMinor;
        });

        return [
            'total_payments_minor' => $totalPaymentsMinor,
            'earned_minor' => $earnedMinor,
            'unearned_minor' => $unearnedMinor,
            'total_refunds_minor' => $totalRefundsMinor,
            'remaining_refundable_minor' => $remainingRefundableMinor,
            'platform_earned_minor' => $platformEarnedMinor,
            'instructor_allocated_minor' => $instructorAllocatedMinor,
            'instructor_paid_minor' => (int) InstructorBalance::query()->sum('total_paid_minor'),
            'instructor_outstanding_minor' => (int) InstructorBalance::query()->sum('outstanding_minor'),
            'active_subscriptions' => Subscription::query()
                ->where('status', SubscriptionStatus::Active)
                ->count(),
            'cancelled_refunded_subscriptions' => Subscription::query()
                ->whereIn('status', [SubscriptionStatus::Cancelled, SubscriptionStatus::Refunded])
                ->count(),
            'pending_payouts' => Payout::query()->where('status', PayoutStatus::Pending)->count(),
            'pending_confirmation_payouts' => Payout::query()
                ->where('status', PayoutStatus::PendingConfirmation)
                ->count(),
            'failed_payouts' => Payout::query()->where('status', PayoutStatus::Failed)->count(),
        ];
    }
}
