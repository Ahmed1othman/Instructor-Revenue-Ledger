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
        private readonly SubscriptionRefundEligibilityService $refundEligibility,
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

        $instructorPoolMinor = $payment !== null
            ? $this->contractualInstructorPoolMinor($subscription, $payment)
            : 0;

        $instructorPoolAllocatedMinor = (int) RevenueAllocation::query()
            ->where('subscription_id', $subscription->id)
            ->sum('allocated_amount_minor');

        $unallocatedInstructorPoolMinor = max(0, $instructorPoolMinor - $instructorPoolAllocatedMinor);
        $platformContractualShareMinor = max(0, $earnedMinor - $instructorPoolMinor);
        $totalPlatformRetainedMinor = $platformContractualShareMinor + $unallocatedInstructorPoolMinor;

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
            platformContractualShareMinor: $platformContractualShareMinor,
            instructorPoolMinor: $instructorPoolMinor,
            instructorPoolAllocatedMinor: $instructorPoolAllocatedMinor,
            unallocatedInstructorPoolMinor: $unallocatedInstructorPoolMinor,
            totalPlatformRetainedMinor: $totalPlatformRetainedMinor,
            instructorPaidMinor: $instructorPaidMinor,
            instructorOutstandingMinor: $instructorOutstandingMinor,
            currency: $currency,
        );
    }

    private function recognizedEarnedMinor(Subscription $subscription, Payment $payment): int
    {
        $recognitionEnd = $this->elapsedRecognitionEnd($subscription);

        if ($recognitionEnd === null) {
            return 0;
        }

        $earnedMinor = 0;
        $cursor = $subscription->starts_at->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($recognitionEnd)) {
            $earnedMinor += $this->recognitionService->earnedAmountMinorForDay(
                $payment,
                $subscription,
                $cursor,
            );
            $cursor->addDay();
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

    private function contractualInstructorPoolMinor(Subscription $subscription, Payment $payment): int
    {
        $recognitionEnd = $this->elapsedRecognitionEnd($subscription);

        if ($recognitionEnd === null) {
            return 0;
        }

        $instructorShareBps = (int) $subscription->plan->instructor_share_bps;
        $poolMinor = 0;
        $cursor = $subscription->starts_at->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($recognitionEnd)) {
            $dayEarned = $this->recognitionService->earnedAmountMinorForDay(
                $payment,
                $subscription,
                $cursor,
            );
            $poolMinor += $this->recognitionService->instructorPoolMinor($dayEarned, $instructorShareBps);
            $cursor->addDay();
        }

        return $poolMinor;
    }

    private function elapsedRecognitionEnd(Subscription $subscription): ?Carbon
    {
        $start = $subscription->starts_at->copy()->startOfDay();
        $end = $subscription->ends_at->copy()->startOfDay();

        if ($subscription->status === SubscriptionStatus::Refunded && $subscription->cancelled_at !== null) {
            $cancelled = Carbon::parse($subscription->cancelled_at)->startOfDay();

            return $cancelled->lessThan($start) ? null : $cancelled;
        }

        $today = now()->startOfDay();

        if ($today->lessThan($start)) {
            return null;
        }

        $recognitionEnd = $today->greaterThan($end) ? $end : $today->copy()->subDay();

        if ($recognitionEnd->lessThan($start)) {
            return null;
        }

        return $recognitionEnd;
    }

    private function remainingRefundableMinor(Subscription $subscription, ?Payment $payment): int
    {
        if ($payment === null || $subscription->status === SubscriptionStatus::Refunded) {
            return 0;
        }

        if ($this->refundEligibility->subscriptionPeriodHasEnded($subscription)) {
            return 0;
        }

        if (now()->startOfDay()->lessThan($subscription->starts_at->copy()->startOfDay())) {
            return 0;
        }

        return $this->refundEligibility->previewRefundAmountMinor(
            $subscription,
            $this->refundEligibility->standardCancellationDate(),
        );
    }
}
