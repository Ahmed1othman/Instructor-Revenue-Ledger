<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\InstructorBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorBalance>
 */
class InstructorBalanceFactory extends Factory
{
    protected $model = InstructorBalance::class;

    public function definition(): array
    {
        return [
            'instructor_id' => Instructor::factory(),
            'currency' => 'USD',
            'total_earned_minor' => 0,
            'total_paid_minor' => 0,
            'outstanding_minor' => 0,
            'last_ledger_entry_id' => null,
        ];
    }
}
