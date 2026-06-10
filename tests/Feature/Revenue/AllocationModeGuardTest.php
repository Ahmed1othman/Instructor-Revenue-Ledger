<?php

use App\Domain\Revenue\Actions\AllocateRevenueForDayAction;
use App\Domain\Revenue\Actions\AllocateRevenueForSettlementAction;
use App\Domain\Revenue\Enums\SettlementGranularity;
use App\Domain\Revenue\Enums\SettlementPeriodStatus;
use App\Domain\Revenue\Exceptions\AllocationModeConflictException;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\LessonConsumption;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\RevenueAllocation;
use App\Models\SettlementPeriod;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-01 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function createJanuaryDailyScenario(): array
{
    $student = User::factory()->create();
    $plan = Plan::factory()->create([
        'price_minor' => 30000,
        'instructor_share_bps' => 6000,
        'duration_days' => 30,
    ]);

    $subscription = Subscription::factory()->create([
        'user_id' => $student->id,
        'plan_id' => $plan->id,
        'starts_at' => '2026-01-01 00:00:00',
        'ends_at' => '2026-01-30 23:59:59',
        'currency' => 'USD',
    ]);

    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => 30000,
        'currency' => 'USD',
    ]);

    $instructor = Instructor::factory()->create();
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);

    LessonConsumption::factory()->create([
        'subscription_id' => $subscription->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
        'valid_watched_seconds' => 3600,
        'consumed_at' => '2026-01-04 10:00:00',
    ]);

    return compact('subscription', 'instructor');
}

it('blocks daily allocation when monthly allocations exist for the month', function (): void {
    createJanuaryDailyScenario();

    $monthlyPeriod = SettlementPeriod::factory()->create([
        'granularity' => SettlementGranularity::Monthly,
        'year' => 2026,
        'month' => 1,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'status' => SettlementPeriodStatus::Open,
    ]);

    app(AllocateRevenueForSettlementAction::class)->execute($monthlyPeriod);

    expect(RevenueAllocation::query()->count())->toBeGreaterThan(0);

    $dailyAction = app(AllocateRevenueForDayAction::class);

    expect(fn () => $dailyAction->execute(Carbon::parse('2026-01-10')))
        ->toThrow(
            AllocationModeConflictException::class,
            'Cannot run daily allocation for 2026-01-10 because monthly allocation already exists for 2026-01.',
        );
});

it('blocks monthly allocation when daily allocations exist for the month', function (): void {
    createJanuaryDailyScenario();

    app(AllocateRevenueForDayAction::class)->execute(Carbon::parse('2026-01-04'));

    expect(RevenueAllocation::query()->count())->toBeGreaterThan(0);

    $monthlyPeriod = SettlementPeriod::factory()->create([
        'granularity' => SettlementGranularity::Monthly,
        'year' => 2026,
        'month' => 1,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'status' => SettlementPeriodStatus::Open,
    ]);

    $monthlyAction = app(AllocateRevenueForSettlementAction::class);

    expect(fn () => $monthlyAction->execute($monthlyPeriod))
        ->toThrow(
            AllocationModeConflictException::class,
            'Cannot run monthly allocation for 2026-01 because daily allocations already exist for this month.',
        );
});
