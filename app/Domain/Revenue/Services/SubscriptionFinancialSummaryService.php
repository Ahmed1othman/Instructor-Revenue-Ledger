<?php

namespace App\Domain\Revenue\Services;

use App\Domain\Revenue\DTOs\SubscriptionFinancialSummary;
use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\RefundStatus;
use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Models\InstructorBalance;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RevenueAllocation;
use App\Models\SettlementPeriod;
use App\Models\Subscription;
use Carbon\Carbon;

class SubscriptionFinancialSummaryService
{
    public function __construct(
        private readonly RevenueRecognitionService $recognitionService,
        private readonly RefundCalculationService $refundCalculation,
    ) {}

    public function forSubscription(Subscription $subscription): SubscriptionFinancialSummary
    {
        $subscription->loadMissing(['plan', 'user']);

        $currency = $subscription->currency;
        $paidMinor = (int) Payment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', PaymentStatus::Succeeded)
            ->sum('amount_minor');

        $payment = Payment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', PaymentStatus::Succeeded)
            ->first();

        $earnedMinor = $payment !== null
            ? $this->recognizedEarnedMinor($subscription, $payment)
            : 0;

        $refundedMinor = (int) Refund::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', RefundStatus::Completed)
            ->sum('amount_minor');

        $unearnedMinor = max(0, $paidMinor - $earnedMinor - $refundedMinor);

        $remainingRefundableMinor = $this->remainingRefundableMinor($subscription, $payment);

        $instructorPoolAllocatedMinor = (int) RevenueAllocation::query()
            ->where('subscription_id', $subscription->id)
            ->sum('allocated_amount_minor');

        $platformEarnedMinor = max(0, $earnedMinor - $instructorPoolAllocatedMinor);

        $instructorIds = RevenueAllocation::query()
            ->where('subscription_id', $subscription->id)
            ->distinct()
            ->pluck('instructor_id');

        $instructorPaidMinor = $instructorIds->isEmpty()
            ? 0
            : (int) InstructorBalance::query()
                ->whereIn('instructor_id', $instructorIds)
                ->where('currency', $currency)
                ->sum('total_paid_minor');

        $instructorOutstandingMinor = $instructorIds->isEmpty()
            ? 0
            : (int) InstructorBalance::query()
                ->whereIn('instructor_id', $instructorIds)
                ->where('currency', $currency)
                ->sum('outstanding_minor');

        return new SubscriptionFinancialSummary(
            paidMinor: $paidMinor,
            earnedMinor: $earnedMinor,
            unearnedMinor: $unearnedMinor,
            refundedMinor: $refundedMinor,
            remainingRefundableMinor: $remainingRefundableMinor,
            platformEarnedMinor: $platformEarnedMinor,
            instructorPoolAllocatedMinor: $instructorPoolAllocatedMinor,
            instructorPaidMinor: $instructorPaidMinor,
            instructorOutstandingMinor: $instructorOutstandingMinor,
            currency: $currency,
        );
    }

    private function recognizedEarnedMinor(Subscription $subscription, Payment $payment): int
    {
        $earnedMinor = 0;

        $dailyDates = RevenueAllocation::query()
            ->where('subscription_id', $subscription->id)
            ->whereNotNull('allocation_date')
            ->distinct()
            ->pluck('allocation_date');

        foreach ($dailyDates as $date) {
            $earnedMinor += $this->recognitionService->earnedAmountMinorForDay(
                $payment,
                $subscription,
                Carbon::parse($date)->startOfDay(),
            );
        }

        $monthlyPeriodIds = RevenueAllocation::query()
            ->where('subscription_id', $subscription->id)
            ->whereNull('allocation_date')
            ->distinct()
            ->pluck('settlement_period_id');

        foreach ($monthlyPeriodIds as $periodId) {
            $period = SettlementPeriod::query()->find($periodId);

            if ($period !== null) {
                $earnedMinor += $this->recognitionService->earnedAmountMinor(
                    $payment,
                    $subscription,
                    $period,
                );
            }
        }

        return $earnedMinor;
    }

    private function remainingRefundableMinor(Subscription $subscription, ?Payment $payment): int
    {
        if ($payment === null || $subscription->status === SubscriptionStatus::Refunded) {
            return 0;
        }

        $today = now()->startOfDay();
        $subscriptionStart = $subscription->starts_at->copy()->startOfDay();
        $subscriptionEnd = $subscription->ends_at->copy()->startOfDay();

        if ($today->lessThan($subscriptionStart)) {
            return 0;
        }

        $cancellationDate = $today->greaterThan($subscriptionEnd) ? $subscriptionEnd : $today;

        return $this->refundCalculation->preview($payment, $subscription, $cancellationDate);
    }
}
