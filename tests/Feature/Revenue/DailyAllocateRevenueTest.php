<?php

use App\Domain\Revenue\Actions\AllocateRevenueForDayAction;
use App\Domain\Revenue\Enums\SettlementGranularity;
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
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-01 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function createDailyAllocationScenario(): array
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

    $instructorA = Instructor::factory()->create(['name' => 'Instructor A']);
    $instructorB = Instructor::factory()->create(['name' => 'Instructor B']);
    $instructorC = Instructor::factory()->create(['name' => 'Instructor C']);

    $courseA = Course::factory()->create(['instructor_id' => $instructorA->id]);
    $courseB = Course::factory()->create(['instructor_id' => $instructorB->id]);
    $courseC = Course::factory()->create(['instructor_id' => $instructorC->id]);

    LessonConsumption::factory()->create([
        'subscription_id' => $subscription->id,
        'student_id' => $student->id,
        'course_id' => $courseA->id,
        'instructor_id' => $instructorA->id,
        'valid_watched_seconds' => 3600,
        'consumed_at' => '2026-01-04 10:00:00',
    ]);

    LessonConsumption::factory()->create([
        'subscription_id' => $subscription->id,
        'student_id' => $student->id,
        'course_id' => $courseB->id,
        'instructor_id' => $instructorB->id,
        'valid_watched_seconds' => 1800,
        'consumed_at' => '2026-01-04 14:00:00',
    ]);

    LessonConsumption::factory()->create([
        'subscription_id' => $subscription->id,
        'student_id' => $student->id,
        'course_id' => $courseC->id,
        'instructor_id' => $instructorC->id,
        'valid_watched_seconds' => 600,
        'consumed_at' => '2026-01-04 18:00:00',
    ]);

    LessonConsumption::factory()->create([
        'subscription_id' => $subscription->id,
        'student_id' => $student->id,
        'course_id' => $courseA->id,
        'instructor_id' => $instructorA->id,
        'valid_watched_seconds' => 9999,
        'consumed_at' => '2026-01-05 10:00:00',
    ]);

    return compact('subscription', 'instructorA', 'instructorB', 'instructorC');
}

it('allocates instructor shares for a single calendar day using engagement on that day only', function (): void {
    ['instructorA' => $a, 'instructorB' => $b, 'instructorC' => $c] = createDailyAllocationScenario();

    $period = app(AllocateRevenueForDayAction::class)->execute(Carbon::parse('2026-01-04'));

    expect($period->granularity)->toBe(SettlementGranularity::Daily);
    expect($period->period_start->toDateString())->toBe('2026-01-04');
    expect($period->period_end->toDateString())->toBe('2026-01-04');

    $allocations = RevenueAllocation::query()
        ->where('allocation_date', '2026-01-04')
        ->get()
        ->keyBy('instructor_id');

    expect($allocations[$a->id]->allocated_amount_minor)->toBe(360);
    expect($allocations[$b->id]->allocated_amount_minor)->toBe(180);
    expect($allocations[$c->id]->allocated_amount_minor)->toBe(60);
    expect($allocations->sum('allocated_amount_minor'))->toBe(600);
    expect($allocations->every(fn ($allocation): bool => $allocation->instructor_pool_minor === 600))->toBeTrue();
});

it('does not duplicate allocations or ledger entries when the same day runs twice', function (): void {
    createDailyAllocationScenario();
    $action = app(AllocateRevenueForDayAction::class);
    $date = Carbon::parse('2026-01-04');

    $action->execute($date);
    $allocationCount = RevenueAllocation::query()->count();
    $ledgerCount = InstructorLedgerEntry::query()->count();

    $action->execute($date);

    expect(RevenueAllocation::query()->count())->toBe($allocationCount);
    expect(InstructorLedgerEntry::query()->count())->toBe($ledgerCount);
});

it('rejects future and current-day allocation dates', function (): void {
    createDailyAllocationScenario();
    $action = app(AllocateRevenueForDayAction::class);

    expect(fn () => $action->execute(Carbon::parse('2026-02-01')))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => $action->execute(Carbon::parse('2026-03-01')))
        ->toThrow(\InvalidArgumentException::class);
});

it('ignores engagement recorded on a different calendar day', function (): void {
    ['instructorA' => $a] = createDailyAllocationScenario();

    app(AllocateRevenueForDayAction::class)->execute(Carbon::parse('2026-01-05'));

    $jan5Allocations = RevenueAllocation::query()
        ->where('allocation_date', '2026-01-05')
        ->get();

    expect($jan5Allocations)->toHaveCount(1);
    expect($jan5Allocations->first()->instructor_id)->toBe($a->id);
    expect($jan5Allocations->first()->allocated_amount_minor)->toBe(600);

    expect(RevenueAllocation::query()->where('allocation_date', '2026-01-04')->count())->toBe(0);
});

it('creates a daily settlement period per calendar date', function (): void {
    createDailyAllocationScenario();
    $action = app(AllocateRevenueForDayAction::class);

    $action->execute(Carbon::parse('2026-01-04'));
    $action->execute(Carbon::parse('2026-01-05'));

    expect(SettlementPeriod::query()->daily()->count())->toBe(2);
});
