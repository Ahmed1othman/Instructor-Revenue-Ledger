<?php

namespace Database\Factories;

use App\Domain\Revenue\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        $cancellationDate = now()->subDays(5)->startOfDay();

        return [
            'subscription_id' => Subscription::factory(),
            'payment_id' => null,
            'student_id' => User::factory(),
            'amount_minor' => 10000,
            'currency' => 'USD',
            'cancellation_date' => $cancellationDate->toDateString(),
            'refund_starts_on' => $cancellationDate->copy()->addDay()->toDateString(),
            'used_days' => 5,
            'unused_days' => 25,
            'status' => RefundStatus::Completed,
            'reason' => 'standard_unused_days',
            'idempotency_key' => 'refund:'.fake()->unique()->numerify('#####').':'.$cancellationDate->toDateString(),
            'processed_at' => now(),
        ];
    }

    public function forSubscription(Subscription $subscription, ?Payment $payment = null): static
    {
        return $this->state(fn (): array => [
            'subscription_id' => $subscription->id,
            'payment_id' => $payment?->id,
            'student_id' => $subscription->user_id,
            'currency' => $subscription->currency,
        ]);
    }
}
