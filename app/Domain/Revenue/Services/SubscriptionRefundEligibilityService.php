<?php

namespace App\Domain\Revenue\Services;

use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\Carbon;
use InvalidArgumentException;

class SubscriptionRefundEligibilityService
{
    public function __construct(
        private readonly RefundCalculationService $refundCalculation,
    ) {}

    public function standardCancellationDate(): Carbon
    {
        return now()->startOfDay();
    }

    public function canOfferStandardRefund(Subscription $subscription): bool
    {
        if ($subscription->status === SubscriptionStatus::Refunded) {
            return false;
        }

        if ($this->subscriptionPeriodHasEnded($subscription)) {
            return false;
        }

        return $this->previewRefundAmountMinor($subscription, $this->standardCancellationDate()) > 0;
    }

    public function validateRefundRequest(Subscription $subscription, Carbon $cancellationDate): void
    {
        if ($subscription->status === SubscriptionStatus::Refunded) {
            throw new InvalidArgumentException('Subscription has already been refunded.');
        }

        $cancellationDay = $cancellationDate->copy()->startOfDay();
        $subscriptionStart = $subscription->starts_at->copy()->startOfDay();
        $subscriptionEnd = $subscription->ends_at->copy()->startOfDay();

        if ($cancellationDay->lessThan($subscriptionStart) || $cancellationDay->greaterThan($subscriptionEnd)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cancellation date %s must be within subscription period %s to %s.',
                    $cancellationDay->toDateString(),
                    $subscriptionStart->toDateString(),
                    $subscriptionEnd->toDateString(),
                ),
            );
        }

        if ($this->subscriptionPeriodHasEnded($subscription)) {
            throw new InvalidArgumentException(
                'Cannot refund a subscription whose access period has already fully ended.',
            );
        }

        if ($this->previewRefundAmountMinor($subscription, $cancellationDay) === 0) {
            throw new InvalidArgumentException(
                'No unused future days remain to refund for the selected cancellation date.',
            );
        }
    }

    public function previewRefundAmountMinor(Subscription $subscription, Carbon $cancellationDate): int
    {
        $payment = Payment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', PaymentStatus::Succeeded)
            ->first();

        if ($payment === null) {
            return 0;
        }

        return $this->refundCalculation->preview($payment, $subscription, $cancellationDate);
    }

    /**
     * @return array{
     *     cancellation_date: string,
     *     refund_starts_on: string,
     *     used_days: int,
     *     unused_days: int,
     *     amount_minor: int
     * }
     */
    public function standardRefundPreview(Subscription $subscription): array
    {
        $payment = Payment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', PaymentStatus::Succeeded)
            ->firstOrFail();

        $cancellationDate = $this->standardCancellationDate();
        $calculation = $this->refundCalculation->calculate($payment, $subscription, $cancellationDate);

        return [
            'cancellation_date' => $cancellationDate->toDateString(),
            ...$calculation,
        ];
    }

    public function subscriptionPeriodHasEnded(Subscription $subscription): bool
    {
        return now()->startOfDay()->greaterThan($subscription->ends_at->copy()->startOfDay());
    }
}
