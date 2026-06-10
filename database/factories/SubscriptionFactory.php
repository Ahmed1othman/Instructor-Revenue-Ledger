<?php

namespace Database\Factories;

use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startsAt = now()->startOfMonth();

        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays(29)->endOfDay(),
            'currency' => 'USD',
        ];
    }
}
