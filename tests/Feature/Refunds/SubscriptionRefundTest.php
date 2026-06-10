<?php

use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Refunds\Actions\CreateSubscriptionRefundAction;
use App\Domain\Revenue\Actions\AllocateRevenueForDayAction;
use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\InstructorLedgerEntry;
use App\Models\LessonConsumption;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\RevenueAllocation;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-01 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function createJanuaryRefundScenario(): array
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

    $payment = Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => 30000,
        'currency' => 'USD',
    ]);

    $instructor = Instructor::factory()->create();
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);

    foreach (range(1, 10) as $day) {
        LessonConsumption::factory()->create([
            'subscription_id' => $subscription->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'instructor_id' => $instructor->id,
            'valid_watched_seconds' => 3600,
            'consumed_at' => sprintf('2026-01-%02d 10:00:00', $day),
        ]);
    }

    return compact('subscription', 'payment', 'instructor', 'student');
}

it('counts cancellation day as used and refunds only unused future days', function (): void {
    ['subscription' => $subscription] = createJanuaryRefundScenario();

    $refund = app(CreateSubscriptionRefundAction::class)->execute(
        $subscription,
        Carbon::parse('2026-01-10'),
    );

    expect($refund->used_days)->toBe(10);
    expect($refund->unused_days)->toBe(20);
    expect($refund->refund_starts_on->toDateString())->toBe('2026-01-11');
    expect($refund->amount_minor)->toBe(20000);
});

it('allocates missing elapsed days through cancellation day before refunding', function (): void {
    ['subscription' => $subscription, 'instructor' => $instructor] = createJanuaryRefundScenario();

    app(AllocateRevenueForDayAction::class)->execute(Carbon::parse('2026-01-01'));
    app(AllocateRevenueForDayAction::class)->execute(Carbon::parse('2026-01-02'));
    app(AllocateRevenueForDayAction::class)->execute(Carbon::parse('2026-01-03'));

    expect(RevenueAllocation::query()->count())->toBe(3);

    app(CreateSubscriptionRefundAction::class)->execute(
        $subscription,
        Carbon::parse('2026-01-10'),
    );

    expect(RevenueAllocation::query()->count())->toBe(10);
    expect(
        RevenueAllocation::query()
            ->where('allocation_date', '2026-01-10')
            ->where('instructor_id', $instructor->id)
            ->exists(),
    )->toBeTrue();
});

it('does not create ledger reversals or clawbacks for standard refunds', function (): void {
    ['subscription' => $subscription] = createJanuaryRefundScenario();

    foreach (range(1, 10) as $day) {
        app(AllocateRevenueForDayAction::class)->execute(
            Carbon::parse(sprintf('2026-01-%02d', $day)),
        );
    }

    $ledgerCountBeforeRefund = InstructorLedgerEntry::query()->count();

    app(CreateSubscriptionRefundAction::class)->execute(
        $subscription,
        Carbon::parse('2026-01-10'),
    );

    expect(InstructorLedgerEntry::query()->count())->toBe($ledgerCountBeforeRefund);
    expect(InstructorLedgerEntry::query()->where('type', 'earning_reversal')->count())->toBe(0);
    expect(InstructorLedgerEntry::query()->where('type', 'clawback')->count())->toBe(0);
    expect(InstructorLedgerEntry::query()->where('type', LedgerEntryType::EarningCredit)->count())
        ->toBe($ledgerCountBeforeRefund);
});

it('returns the existing refund when the same cancellation date is processed twice', function (): void {
    ['subscription' => $subscription] = createJanuaryRefundScenario();
    $action = app(CreateSubscriptionRefundAction::class);
    $cancellationDate = Carbon::parse('2026-01-10');

    $first = $action->execute($subscription, $cancellationDate);
    $ledgerCountAfterFirst = InstructorLedgerEntry::query()->count();

    $second = $action->execute($subscription->fresh(), $cancellationDate);

    expect($second->id)->toBe($first->id);
    expect(Refund::query()->count())->toBe(1);
    expect(InstructorLedgerEntry::query()->count())->toBe($ledgerCountAfterFirst);
});

it('marks the subscription as refunded with cancellation metadata', function (): void {
    ['subscription' => $subscription] = createJanuaryRefundScenario();

    app(CreateSubscriptionRefundAction::class)->execute(
        $subscription,
        Carbon::parse('2026-01-10'),
    );

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Refunded);
    expect($subscription->cancelled_at->toDateString())->toBe('2026-01-10');
    expect($subscription->refunded_at)->not->toBeNull();
});
