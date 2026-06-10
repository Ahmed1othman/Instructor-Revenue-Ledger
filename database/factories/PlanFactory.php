<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'price_minor' => 30000,
            'currency' => 'USD',
            'instructor_share_bps' => 6000,
            'duration_days' => 30,
        ];
    }
}
