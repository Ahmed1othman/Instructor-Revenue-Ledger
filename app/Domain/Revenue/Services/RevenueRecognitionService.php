<?php

namespace App\Domain\Revenue\Services;

use App\Models\Payment;
use App\Models\SettlementPeriod;
use App\Models\Subscription;
use Carbon\Carbon;

class RevenueRecognitionService
{
    public function earnedAmountMinor(
        Payment $payment,
        Subscription $subscription,
        SettlementPeriod $period,
    ): int {
        $overlapDays = $this->overlapDays($subscription, $period);

        if ($overlapDays === 0) {
            return 0;
        }

        $totalDays = $this->subscriptionDays($subscription);
        $earned = intdiv($payment->amount_minor * $overlapDays, $totalDays);

        if ($this->isLastOverlappingPeriod($subscription, $period)) {
            $earned += $this->lifetimeRemainderMinor($payment, $subscription);
        }

        return $earned;
    }

    public function earnedAmountMinorForDay(
        Payment $payment,
        Subscription $subscription,
        Carbon $date,
    ): int {
        if (! $this->isSubscriptionActiveOnDate($subscription, $date)) {
            return 0;
        }

        $totalDays = $this->subscriptionDays($subscription);
        $earned = intdiv($payment->amount_minor, $totalDays);

        if ($this->isLastSubscriptionDay($subscription, $date)) {
            $earned += $this->dailyLifetimeRemainderMinor($payment, $subscription);
        }

        return $earned;
    }

    public function isSubscriptionActiveOnDate(Subscription $subscription, Carbon $date): bool
    {
        $day = $date->copy()->startOfDay();

        return $subscription->starts_at->copy()->startOfDay()->lessThanOrEqualTo($day)
            && $subscription->ends_at->copy()->endOfDay()->greaterThanOrEqualTo($day);
    }

    public function isLastSubscriptionDay(Subscription $subscription, Carbon $date): bool
    {
        return $subscription->ends_at->copy()->startOfDay()->equalTo($date->copy()->startOfDay());
    }

    public function unusedFutureDaysAmountMinor(
        Payment $payment,
        Subscription $subscription,
        Carbon $cancellationDate,
    ): int {
        $refundStartsOn = $cancellationDate->copy()->addDay()->startOfDay();
        $subscriptionEnd = $subscription->ends_at->copy()->startOfDay();

        if ($refundStartsOn->greaterThan($subscriptionEnd)) {
            return 0;
        }

        $sum = 0;
        $cursor = $refundStartsOn->copy();

        while ($cursor->lessThanOrEqualTo($subscriptionEnd)) {
            $sum += $this->earnedAmountMinorForDay($payment, $subscription, $cursor);
            $cursor = $cursor->addDay();
        }

        return $sum;
    }

    public function lifetimeDailyRecognizedMinor(Payment $payment, Subscription $subscription): int
    {
        $start = $subscription->starts_at->copy()->startOfDay();
        $end = $subscription->ends_at->copy()->startOfDay();
        $sum = 0;
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $sum += $this->earnedAmountMinorForDay($payment, $subscription, $cursor);
            $cursor = $cursor->addDay();
        }

        return $sum;
    }

    public function instructorPoolMinor(int $earnedAmountMinor, int $instructorShareBps): int
    {
        return intdiv($earnedAmountMinor * $instructorShareBps, 10000);
    }

    public function platformShareMinor(int $earnedAmountMinor, int $instructorPoolMinor): int
    {
        return $earnedAmountMinor - $instructorPoolMinor;
    }

    public function overlapDays(Subscription $subscription, SettlementPeriod $period): int
    {
        $subscriptionStart = $subscription->starts_at->copy()->startOfDay();
        $subscriptionEnd = $subscription->ends_at->copy()->startOfDay();
        $periodStart = $period->period_start->copy()->startOfDay();
        $periodEnd = $period->period_end->copy()->startOfDay();

        $overlapStart = $subscriptionStart->greaterThan($periodStart) ? $subscriptionStart : $periodStart;
        $overlapEnd = $subscriptionEnd->lessThan($periodEnd) ? $subscriptionEnd : $periodEnd;

        if ($overlapEnd->lessThan($overlapStart)) {
            return 0;
        }

        return $overlapStart->diffInDays($overlapEnd) + 1;
    }

    public function subscriptionDays(Subscription $subscription): int
    {
        $start = $subscription->starts_at->copy()->startOfDay();
        $end = $subscription->ends_at->copy()->startOfDay();

        return $start->diffInDays($end) + 1;
    }

    /**
     * @return array<int, SettlementPeriod>
     */
    public function overlappingPeriods(Subscription $subscription): array
    {
        $periods = [];
        $cursor = $subscription->starts_at->copy()->startOfMonth();
        $subscriptionEnd = $subscription->ends_at->copy()->endOfMonth();

        while ($cursor->lessThanOrEqualTo($subscriptionEnd)) {
            $period = new SettlementPeriod([
                'year' => (int) $cursor->year,
                'month' => (int) $cursor->month,
                'period_start' => $cursor->copy()->startOfMonth()->toDateString(),
                'period_end' => $cursor->copy()->endOfMonth()->toDateString(),
            ]);

            if ($this->overlapDays($subscription, $period) > 0) {
                $periods[] = $period;
            }

            $cursor = $cursor->addMonthNoOverflow();
        }

        return $periods;
    }

    public function lifetimeRecognizedMinor(Payment $payment, Subscription $subscription): int
    {
        $totalDays = $this->subscriptionDays($subscription);
        $sum = 0;

        foreach ($this->overlappingPeriods($subscription) as $period) {
            $overlapDays = $this->overlapDays($subscription, $period);
            $sum += intdiv($payment->amount_minor * $overlapDays, $totalDays);
        }

        $remainder = $payment->amount_minor - $sum;

        return $sum + $remainder;
    }

    private function lifetimeRemainderMinor(Payment $payment, Subscription $subscription): int
    {
        $totalDays = $this->subscriptionDays($subscription);
        $sum = 0;

        foreach ($this->overlappingPeriods($subscription) as $period) {
            $overlapDays = $this->overlapDays($subscription, $period);
            $sum += intdiv($payment->amount_minor * $overlapDays, $totalDays);
        }

        return $payment->amount_minor - $sum;
    }

    private function dailyLifetimeRemainderMinor(Payment $payment, Subscription $subscription): int
    {
        $totalDays = $this->subscriptionDays($subscription);
        $perDay = intdiv($payment->amount_minor, $totalDays);

        return $payment->amount_minor - ($perDay * $totalDays);
    }

    private function isLastOverlappingPeriod(Subscription $subscription, SettlementPeriod $period): bool
    {
        $periods = $this->overlappingPeriods($subscription);

        if ($periods === []) {
            return false;
        }

        $last = end($periods);

        return $last->year === $period->year && $last->month === $period->month;
    }
}
