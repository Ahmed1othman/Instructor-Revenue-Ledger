<?php

namespace App\Domain\Refunds\Actions;

use App\Domain\Revenue\Actions\EnsureElapsedDaysAllocatedAction;
use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\RefundStatus;
use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Domain\Revenue\Services\RefundCalculationService;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateSubscriptionRefundAction
{
    public function __construct(
        private readonly EnsureElapsedDaysAllocatedAction $ensureElapsedDaysAllocated,
        private readonly RefundCalculationService $refundCalculation,
    ) {}

    public function execute(Subscription $subscription, Carbon $cancellationDate): Refund
    {
        $cancellationDay = $cancellationDate->copy()->startOfDay();
        $idempotencyKey = sprintf(
            'refund:%d:%s',
            $subscription->id,
            $cancellationDay->toDateString(),
        );

        $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

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

        $payment = Payment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', PaymentStatus::Succeeded)
            ->first();

        if ($payment === null) {
            throw new InvalidArgumentException(
                sprintf('No succeeded payment found for subscription %d.', $subscription->id),
            );
        }

        $this->ensureElapsedDaysAllocated->execute($subscription, $cancellationDay);

        $calculation = $this->refundCalculation->calculate($payment, $subscription, $cancellationDay);

        return DB::transaction(function () use (
            $subscription,
            $payment,
            $cancellationDay,
            $calculation,
            $idempotencyKey,
        ): Refund {
            $refund = Refund::query()->create([
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'student_id' => $subscription->user_id,
                'amount_minor' => $calculation['amount_minor'],
                'currency' => $payment->currency,
                'cancellation_date' => $cancellationDay->toDateString(),
                'refund_starts_on' => $calculation['refund_starts_on'],
                'used_days' => $calculation['used_days'],
                'unused_days' => $calculation['unused_days'],
                'status' => RefundStatus::Completed,
                'reason' => 'standard_unused_days',
                'idempotency_key' => $idempotencyKey,
                'processed_at' => now(),
            ]);

            $subscription->update([
                'cancelled_at' => $cancellationDay->toDateString(),
                'refunded_at' => now(),
                'status' => SubscriptionStatus::Refunded,
            ]);

            return $refund;
        });
    }
}
