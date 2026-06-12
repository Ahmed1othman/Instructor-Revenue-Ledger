<?php

use App\Domain\Revenue\Actions\AllocateRevenueForDayAction;
use App\Domain\Revenue\Services\SubscriptionFinancialSummaryService;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\RevenueAllocation;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-01-05 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('retains instructor pool on the platform when an elapsed day has no engagement', function (): void {
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

    foreach (['2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04'] as $day) {
        app(AllocateRevenueForDayAction::class)->execute(Carbon::parse($day));
    }

    expect(RevenueAllocation::query()->where('subscription_id', $subscription->id)->count())->toBe(0);

    $summary = app(SubscriptionFinancialSummaryService::class)->forSubscription($subscription);

    expect($summary->earnedMinor)->toBe(4000);
    expect($summary->instructorPoolAllocatedMinor)->toBe(0);
    expect($summary->unallocatedInstructorPoolMinor)->toBe(2400);
    expect($summary->platformContractualShareMinor)->toBe(1600);
    expect($summary->totalPlatformRetainedMinor)->toBe(4000);
    expect($summary->instructorOutstandingMinor)->toBe(0);
});
