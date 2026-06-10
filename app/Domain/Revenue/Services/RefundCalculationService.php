<?php

namespace App\Domain\Revenue\Services;

use App\Models\Payment;
use App\Models\Subscription;
use Carbon\Carbon;

class RefundCalculationService
{
    public function __construct(
        private readonly RevenueRecognitionService $recognitionService,
    ) {}

    public function usedDays(Subscription $subscription, Carbon $cancellationDate): int
    {
        $start = $subscription->starts_at->copy()->startOfDay();
        $cancel = $cancellationDate->copy()->startOfDay();

        return $start->diffInDays($cancel) + 1;
    }

    public function unusedDays(Subscription $subscription, Carbon $cancellationDate): int
    {
        $refundStartsOn = $cancellationDate->copy()->addDay()->startOfDay();
        $subscriptionEnd = $subscription->ends_at->copy()->startOfDay();

        if ($refundStartsOn->greaterThan($subscriptionEnd)) {
            return 0;
        }

        return $refundStartsOn->diffInDays($subscriptionEnd) + 1;
    }

    /**
     * @return array{
     *     used_days: int,
     *     unused_days: int,
     *     refund_starts_on: string,
     *     amount_minor: int
     * }
     */
    public function calculate(
        Payment $payment,
        Subscription $subscription,
        Carbon $cancellationDate,
    ): array {
        return [
            'used_days' => $this->usedDays($subscription, $cancellationDate),
            'unused_days' => $this->unusedDays($subscription, $cancellationDate),
            'refund_starts_on' => $cancellationDate->copy()->addDay()->toDateString(),
            'amount_minor' => $this->recognitionService->unusedFutureDaysAmountMinor(
                $payment,
                $subscription,
                $cancellationDate,
            ),
        ];
    }

    public function preview(
        Payment $payment,
        Subscription $subscription,
        Carbon $cancellationDate,
    ): int {
        return $this->recognitionService->unusedFutureDaysAmountMinor(
            $payment,
            $subscription,
            $cancellationDate,
        );
    }
}
