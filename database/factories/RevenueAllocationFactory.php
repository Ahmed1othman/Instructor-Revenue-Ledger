<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\RevenueAllocation;
use App\Models\SettlementPeriod;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RevenueAllocation>
 */
class RevenueAllocationFactory extends Factory
{
    protected $model = RevenueAllocation::class;

    public function definition(): array
    {
        return [
            'settlement_period_id' => SettlementPeriod::factory(),
            'subscription_id' => Subscription::factory(),
            'instructor_id' => Instructor::factory(),
            'instructor_pool_minor' => 18000,
            'engagement_weight' => 3600,
            'allocated_amount_minor' => 10800,
            'currency' => 'USD',
            'idempotency_key' => 'allocation:'.Str::uuid(),
        ];
    }
}
