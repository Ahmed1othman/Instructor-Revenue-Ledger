<?php

namespace Database\Factories;

use App\Domain\Revenue\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'amount_minor' => 30000,
            'currency' => 'USD',
            'status' => PaymentStatus::Succeeded,
            'paid_at' => now(),
            'idempotency_key' => 'payment:'.Str::uuid(),
        ];
    }
}
