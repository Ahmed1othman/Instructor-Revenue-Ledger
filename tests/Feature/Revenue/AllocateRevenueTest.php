<?php

use App\Domain\Revenue\Actions\AllocateRevenueForSettlementAction;
use App\Domain\Revenue\Enums\SettlementPeriodStatus;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\InstructorLedgerEntry;
use App\Models\LessonConsumption;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\RevenueAllocation;
use App\Models\SettlementPeriod;
use App\Models\Subscription;
use App\Models\User;

function createDemoAllocationScenario(): array
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
        'starts_at' => now()->startOfMonth(),
        'ends_at' => now()->startOfMonth()->addDays(29)->endOfDay(),
        'currency' => 'USD',
    ]);

    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => 30000,
        'currency' => 'USD',
    ]);

    $instructorA = Instructor::factory()->create(['name' => 'Instructor A']);
    $instructorB = Instructor::factory()->create(['name' => 'Instructor B']);
    $instructorC = Instructor::factory()->create(['name' => 'Instructor C']);

    $courseA = Course::factory()->create(['instructor_id' => $instructorA->id]);
    $courseB = Course::factory()->create(['instructor_id' => $instructorB->id]);
    $courseC = Course::factory()->create(['instructor_id' => $instructorC->id]);

    $consumedAt = now()->startOfMonth()->addDays(3);

    LessonConsumption::factory()->create([
        'subscription_id' => $subscription->id,
        'student_id' => $student->id,
        'course_id' => $courseA->id,
        'instructor_id' => $instructorA->id,
        'valid_watched_seconds' => 3600,
        'consumed_at' => $consumedAt,
    ]);

    LessonConsumption::factory()->create([
        'subscription_id' => $subscription->id,
        'student_id' => $student->id,
        'course_id' => $courseB->id,
        'instructor_id' => $instructorB->id,
        'valid_watched_seconds' => 1800,
        'consumed_at' => $consumedAt,
    ]);

    LessonConsumption::factory()->create([
        'subscription_id' => $subscription->id,
        'student_id' => $student->id,
        'course_id' => $courseC->id,
        'instructor_id' => $instructorC->id,
        'valid_watched_seconds' => 600,
        'consumed_at' => $consumedAt,
    ]);

    $period = SettlementPeriod::factory()->create([
        'year' => (int) now()->year,
        'month' => (int) now()->month,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'status' => SettlementPeriodStatus::Open,
    ]);

    return compact(
        'subscription',
        'period',
        'instructorA',
        'instructorB',
        'instructorC',
    );
}

it('allocates instructor shares using valid_watched_seconds weights', function (): void {
    ['period' => $period, 'instructorA' => $a, 'instructorB' => $b, 'instructorC' => $c] = createDemoAllocationScenario();

    app(AllocateRevenueForSettlementAction::class)->execute($period);

    $allocations = RevenueAllocation::query()
        ->where('settlement_period_id', $period->id)
        ->get()
        ->keyBy('instructor_id');

    expect($allocations[$a->id]->allocated_amount_minor)->toBe(10800);
    expect($allocations[$b->id]->allocated_amount_minor)->toBe(5400);
    expect($allocations[$c->id]->allocated_amount_minor)->toBe(1800);
    expect($allocations->sum('allocated_amount_minor'))->toBe(18000);
});

it('skips instructor earnings when there is no engagement', function (): void {
    $student = User::factory()->create();
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->create([
        'user_id' => $student->id,
        'plan_id' => $plan->id,
        'starts_at' => now()->startOfMonth(),
        'ends_at' => now()->startOfMonth()->addDays(29)->endOfDay(),
    ]);

    Payment::factory()->create(['subscription_id' => $subscription->id, 'amount_minor' => 30000]);

    $period = SettlementPeriod::factory()->create([
        'year' => (int) now()->year,
        'month' => (int) now()->month,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    app(AllocateRevenueForSettlementAction::class)->execute($period);

    expect(RevenueAllocation::query()->count())->toBe(0);
    expect(InstructorLedgerEntry::query()->count())->toBe(0);
});

it('does not create duplicate earning ledger entries when allocation runs twice', function (): void {
    ['period' => $period] = createDemoAllocationScenario();
    $action = app(AllocateRevenueForSettlementAction::class);

    $action->execute($period);
    $firstCount = InstructorLedgerEntry::query()->count();

    $action->execute($period);

    expect(InstructorLedgerEntry::query()->count())->toBe($firstCount);
    expect(RevenueAllocation::query()->count())->toBe(3);
});
