<?php

namespace Database\Seeders;

use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\LessonConsumption;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoFinancialCoreSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@demo.local';

    public const STUDENT_EMAIL = 'student@demo.local';

    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Admin User',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        $student = User::query()->updateOrCreate(
            ['email' => self::STUDENT_EMAIL],
            [
                'name' => 'Student User',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        $plan = Plan::query()->updateOrCreate(
            ['name' => 'Monthly Pro'],
            [
                'price_minor' => 30000,
                'currency' => 'EGP',
                'instructor_share_bps' => 6000,
                'duration_days' => 30,
            ],
        );

        $startsAt = '2026-01-01 00:00:00';
        $endsAt = '2026-01-30 23:59:59';

        $subscription = Subscription::query()->updateOrCreate(
            [
                'user_id' => $student->id,
                'plan_id' => $plan->id,
                'starts_at' => $startsAt,
            ],
            [
                'status' => SubscriptionStatus::Active,
                'ends_at' => $endsAt,
                'currency' => 'EGP',
            ],
        );

        Payment::query()->updateOrCreate(
            ['idempotency_key' => 'demo:payment:jan-2026'],
            [
                'subscription_id' => $subscription->id,
                'amount_minor' => 30000,
                'currency' => 'EGP',
                'status' => PaymentStatus::Succeeded,
                'paid_at' => '2026-01-01 12:00:00',
            ],
        );

        $demoInstructors = [
            ['name' => 'Instructor A', 'course' => 'Laravel APIs', 'seconds' => 3600],
            ['name' => 'Instructor B', 'course' => 'Livewire & Filament', 'seconds' => 1800],
            ['name' => 'Instructor C', 'course' => 'Career Skills', 'seconds' => 600],
        ];

        $consumedAt = '2026-01-04 10:00:00';

        foreach ($demoInstructors as $data) {
            $instructor = Instructor::query()->updateOrCreate(
                ['name' => $data['name']],
                ['user_id' => null],
            );

            $course = Course::query()->updateOrCreate(
                [
                    'instructor_id' => $instructor->id,
                    'title' => $data['course'],
                ],
                [],
            );

            LessonConsumption::query()->updateOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'instructor_id' => $instructor->id,
                ],
                [
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'valid_watched_seconds' => $data['seconds'],
                    'consumed_at' => $consumedAt,
                ],
            );
        }

        $this->command?->info('Demo financial core data seeded.');
        $this->command?->info(sprintf('Filament admin: %s / %s', self::ADMIN_EMAIL, self::DEMO_PASSWORD));
        $this->command?->info(sprintf('Student user: %s / %s', self::STUDENT_EMAIL, self::DEMO_PASSWORD));
    }
}
