<?php

namespace Database\Seeders;

use App\Domain\Payouts\Actions\CreatePayoutBatchAction;
use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Refunds\Actions\CreateSubscriptionRefundAction;
use App\Domain\Revenue\Actions\AllocateRevenueForDayAction;
use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\InstructorBalance;
use App\Models\LessonConsumption;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RichFinancialDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $plans = $this->seedPlans();
        $instructors = $this->seedInstructors();
        $students = $this->seedStudents();

        $allocate = app(AllocateRevenueForDayAction::class);
        $today = now()->startOfDay();

        $activeMonthly = $this->createSubscription(
            student: $students[0],
            plan: $plans['monthly'],
            startsAt: $today->copy()->subDays(20),
            endsAt: $today->copy()->addDays(10),
            status: SubscriptionStatus::Active,
            consumptions: [
                [$instructors[0], 3600, $today->copy()->subDays(15)],
                [$instructors[1], 2400, $today->copy()->subDays(10)],
                [$instructors[0], 1800, $today->copy()->subDays(5)],
            ],
        );

        $activeQuarterly = $this->createSubscription(
            student: $students[1],
            plan: $plans['quarterly'],
            startsAt: $today->copy()->subDays(45),
            endsAt: $today->copy()->addDays(45),
            status: SubscriptionStatus::Active,
            consumptions: [
                [$instructors[2], 5400, $today->copy()->subDays(30)],
                [$instructors[3], 4200, $today->copy()->subDays(20)],
                [$instructors[2], 3000, $today->copy()->subDays(8)],
            ],
        );

        $noEngagement = $this->createSubscription(
            student: $students[2],
            plan: $plans['monthly'],
            startsAt: $today->copy()->subDays(12),
            endsAt: $today->copy()->addDays(18),
            status: SubscriptionStatus::Active,
            consumptions: [],
        );

        $multiInstructor = $this->createSubscription(
            student: $students[3],
            plan: $plans['annual'],
            startsAt: $today->copy()->subDays(60),
            endsAt: $today->copy()->addDays(305),
            status: SubscriptionStatus::Active,
            consumptions: [
                [$instructors[0], 7200, $today->copy()->subDays(40)],
                [$instructors[1], 6000, $today->copy()->subDays(35)],
                [$instructors[2], 4800, $today->copy()->subDays(25)],
                [$instructors[3], 3600, $today->copy()->subDays(12)],
            ],
        );

        $expired = $this->createSubscription(
            student: $students[4],
            plan: $plans['monthly'],
            startsAt: $today->copy()->subDays(50),
            endsAt: $today->copy()->subDays(20),
            status: SubscriptionStatus::Expired,
            consumptions: [
                [$instructors[1], 3000, $today->copy()->subDays(42)],
            ],
        );

        $cancelled = $this->createSubscription(
            student: $students[5],
            plan: $plans['quarterly'],
            startsAt: $today->copy()->subDays(30),
            endsAt: $today->copy()->addDays(60),
            status: SubscriptionStatus::Cancelled,
            consumptions: [
                [$instructors[2], 2400, $today->copy()->subDays(22)],
            ],
            cancelledAt: $today->copy()->subDays(7),
        );

        $refundCandidate = $this->createSubscription(
            student: $students[0],
            plan: $plans['monthly'],
            startsAt: $today->copy()->subDays(25),
            endsAt: $today->copy()->addDays(5),
            status: SubscriptionStatus::Active,
            consumptions: [
                [$instructors[3], 3600, $today->copy()->subDays(18)],
                [$instructors[3], 2400, $today->copy()->subDays(9)],
            ],
        );

        foreach ([$activeMonthly, $activeQuarterly, $noEngagement, $multiInstructor, $expired, $cancelled, $refundCandidate] as $subscription) {
            $this->allocateElapsedDays($allocate, $subscription, $today->copy()->subDay());
        }

        if ($refundCandidate->ends_at->copy()->startOfDay()->greaterThanOrEqualTo($today)) {
            app(CreateSubscriptionRefundAction::class)->execute(
                $refundCandidate->fresh(),
                $today->copy(),
            );
        }

        $this->seedDemoPayouts($instructors);

        $this->command?->info('Rich financial demo data seeded.');
        $this->command?->info(sprintf('Admin: %s / %s', DemoFinancialCoreSeeder::ADMIN_EMAIL, DemoFinancialCoreSeeder::DEMO_PASSWORD));
        $this->command?->info('Run daily allocation for new elapsed days: php artisan revenue:allocate --date=YYYY-MM-DD');
    }

    private function seedUsers(): void
    {
        User::query()->updateOrCreate(
            ['email' => DemoFinancialCoreSeeder::ADMIN_EMAIL],
            [
                'name' => 'Admin User',
                'password' => Hash::make(DemoFinancialCoreSeeder::DEMO_PASSWORD),
                'email_verified_at' => now(),
                'is_admin' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => DemoFinancialCoreSeeder::STUDENT_EMAIL],
            [
                'name' => 'Student User',
                'password' => Hash::make(DemoFinancialCoreSeeder::DEMO_PASSWORD),
                'email_verified_at' => now(),
                'is_admin' => false,
            ],
        );
    }

    /**
     * @return array<string, Plan>
     */
    private function seedPlans(): array
    {
        return [
            'monthly' => Plan::query()->updateOrCreate(
                ['name' => 'Monthly Pro'],
                [
                    'price_minor' => 30000,
                    'currency' => 'EGP',
                    'instructor_share_bps' => 6000,
                    'duration_days' => 30,
                ],
            ),
            'quarterly' => Plan::query()->updateOrCreate(
                ['name' => 'Quarterly Growth'],
                [
                    'price_minor' => 75000,
                    'currency' => 'EGP',
                    'instructor_share_bps' => 5500,
                    'duration_days' => 90,
                ],
            ),
            'annual' => Plan::query()->updateOrCreate(
                ['name' => 'Annual Elite'],
                [
                    'price_minor' => 240000,
                    'currency' => 'EGP',
                    'instructor_share_bps' => 5000,
                    'duration_days' => 365,
                ],
            ),
        ];
    }

    /**
     * @return array<int, Instructor>
     */
    private function seedInstructors(): array
    {
        $names = ['Sara Hassan', 'Omar Farouk', 'Lina Nabil', 'Karim Adel'];

        return array_map(
            fn (string $name): Instructor => Instructor::query()->updateOrCreate(['name' => $name], ['user_id' => null]),
            $names,
        );
    }

    /**
     * @return array<int, User>
     */
    private function seedStudents(): array
    {
        $students = [];

        foreach (range(1, 6) as $index) {
            $students[] = User::query()->updateOrCreate(
                ['email' => sprintf('student%d@demo.local', $index)],
                [
                    'name' => sprintf('Demo Student %d', $index),
                    'password' => Hash::make(DemoFinancialCoreSeeder::DEMO_PASSWORD),
                    'email_verified_at' => now(),
                    'is_admin' => false,
                ],
            );
        }

        return $students;
    }

    /**
     * @param  array<int, array{0: Instructor, 1: int, 2: Carbon}>  $consumptions
     */
    private function createSubscription(
        User $student,
        Plan $plan,
        Carbon $startsAt,
        Carbon $endsAt,
        SubscriptionStatus $status,
        array $consumptions,
        ?Carbon $cancelledAt = null,
    ): Subscription {
        $subscription = Subscription::query()->updateOrCreate(
            [
                'user_id' => $student->id,
                'plan_id' => $plan->id,
                'starts_at' => $startsAt->toDateTimeString(),
            ],
            [
                'status' => $status,
                'ends_at' => $endsAt->endOfDay()->toDateTimeString(),
                'cancelled_at' => $cancelledAt?->toDateString(),
                'currency' => $plan->currency,
            ],
        );

        Payment::query()->updateOrCreate(
            ['idempotency_key' => 'rich:payment:'.$subscription->id],
            [
                'subscription_id' => $subscription->id,
                'amount_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'status' => PaymentStatus::Succeeded,
                'paid_at' => $startsAt->copy()->addHours(12),
            ],
        );

        foreach ($consumptions as [$instructor, $seconds, $consumedAt]) {
            $course = Course::query()->firstOrCreate(
                [
                    'instructor_id' => $instructor->id,
                    'title' => $instructor->name.' Course',
                ],
                [],
            );

            LessonConsumption::query()->updateOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'instructor_id' => $instructor->id,
                    'consumed_at' => $consumedAt->toDateTimeString(),
                ],
                [
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'valid_watched_seconds' => $seconds,
                ],
            );
        }

        return $subscription->fresh();
    }

    private function allocateElapsedDays(AllocateRevenueForDayAction $allocate, Subscription $subscription, Carbon $through): void
    {
        $cursor = $subscription->starts_at->copy()->startOfDay();
        $end = $through->copy()->startOfDay();

        if ($end->greaterThan($subscription->ends_at->copy()->startOfDay())) {
            $end = $subscription->ends_at->copy()->startOfDay();
        }

        while ($cursor->lessThanOrEqualTo($end)) {
            if ($cursor->lessThan(now()->startOfDay())) {
                try {
                    $allocate->execute($cursor);
                } catch (\InvalidArgumentException) {
                    // Skip days blocked by mode guard or already settled in mixed demo data.
                }
            }

            $cursor->addDay();
        }
    }

    /**
     * @param  array<int, Instructor>  $instructors
     */
    private function seedDemoPayouts(array $instructors): void
    {
        $batch = app(CreatePayoutBatchAction::class)->execute();

        $statuses = [
            PayoutStatus::Pending,
            PayoutStatus::Succeeded,
            PayoutStatus::Failed,
            PayoutStatus::PendingConfirmation,
        ];

        foreach ($instructors as $index => $instructor) {
            $balance = InstructorBalance::query()->firstOrCreate(
                [
                    'instructor_id' => $instructor->id,
                    'currency' => 'EGP',
                ],
                [
                    'total_earned_minor' => 0,
                    'total_paid_minor' => 0,
                    'outstanding_minor' => 0,
                ],
            );

            if ($balance->outstanding_minor === 0) {
                continue;
            }

            $amount = min($balance->outstanding_minor, 5000 + ($index * 1500));

            Payout::query()->updateOrCreate(
                ['provider_idempotency_key' => 'rich:payout:'.$instructor->id.':'.$index],
                [
                    'payout_batch_id' => $batch->id,
                    'instructor_id' => $instructor->id,
                    'amount_minor' => $amount,
                    'currency' => 'EGP',
                    'status' => $statuses[$index % count($statuses)],
                    'balance_snapshot_hash' => hash('sha256', 'rich-demo-'.$instructor->id),
                    'active_snapshot_key' => null,
                ],
            );
        }
    }
}
