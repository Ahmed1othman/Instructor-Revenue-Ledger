<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\LessonConsumption;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonConsumption>
 */
class LessonConsumptionFactory extends Factory
{
    protected $model = LessonConsumption::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'student_id' => User::factory(),
            'course_id' => Course::factory(),
            'instructor_id' => Instructor::factory(),
            'valid_watched_seconds' => 600,
            'consumed_at' => now()->startOfMonth()->addDays(2),
        ];
    }
}
