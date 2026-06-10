<?php

use App\Domain\Revenue\Services\RevenueRecognitionService;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\SettlementPeriod;
use App\Models\Subscription;
use Carbon\Carbon;

it('recognizes full payment amount for full-month overlap', function (): void {
    $service = new RevenueRecognitionService;
    $startsAt = Carbon::parse('2026-01-01')->startOfDay();
    $plan = new Plan(['instructor_share_bps' => 6000]);
    $subscription = new Subscription([
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addDays(29)->endOfDay(),
    ]);
    $subscription->setRelation('plan', $plan);

    $payment = new Payment(['amount_minor' => 30000, 'currency' => 'USD']);
    $period = new SettlementPeriod([
        'year' => 2026,
        'month' => 1,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
    ]);

    $earned = $service->earnedAmountMinor($payment, $subscription, $period);
    $pool = $service->instructorPoolMinor($earned, $plan->instructor_share_bps);

    expect($earned)->toBe(30000);
    expect($pool)->toBe(18000);
});

it('prorates earned revenue for partial overlap at a period boundary', function (): void {
    $service = new RevenueRecognitionService;
    $subscription = new Subscription([
        'starts_at' => Carbon::parse('2026-01-15')->startOfDay(),
        'ends_at' => Carbon::parse('2026-02-14')->endOfDay(),
    ]);

    $payment = new Payment(['amount_minor' => 30000, 'currency' => 'USD']);

    $january = new SettlementPeriod([
        'year' => 2026,
        'month' => 1,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
    ]);

    $february = new SettlementPeriod([
        'year' => 2026,
        'month' => 2,
        'period_start' => '2026-02-01',
        'period_end' => '2026-02-28',
    ]);

    $januaryEarned = $service->earnedAmountMinor($payment, $subscription, $january);
    $februaryEarned = $service->earnedAmountMinor($payment, $subscription, $february);

    expect($januaryEarned)->toBeGreaterThan(0);
    expect($februaryEarned)->toBeGreaterThan(0);
    expect($januaryEarned)->toBeLessThan(30000);
    expect($februaryEarned)->toBeLessThan(30000);
});

it('recognizes lifetime revenue equal to the original payment amount', function (): void {
    $service = new RevenueRecognitionService;
    $subscription = new Subscription([
        'starts_at' => Carbon::parse('2026-01-15')->startOfDay(),
        'ends_at' => Carbon::parse('2026-03-14')->endOfDay(),
    ]);

    $payment = new Payment(['amount_minor' => 30000, 'currency' => 'USD']);

    expect($service->lifetimeRecognizedMinor($payment, $subscription))->toBe(30000);
});

it('recognizes a uniform daily amount with remainder on the last subscription day', function (): void {
    $service = new RevenueRecognitionService;
    $subscription = new Subscription([
        'starts_at' => Carbon::parse('2026-01-01')->startOfDay(),
        'ends_at' => Carbon::parse('2026-01-30')->endOfDay(),
    ]);
    $payment = new Payment(['amount_minor' => 30000, 'currency' => 'USD']);

    $firstDay = $service->earnedAmountMinorForDay($payment, $subscription, Carbon::parse('2026-01-01'));
    $lastDay = $service->earnedAmountMinorForDay($payment, $subscription, Carbon::parse('2026-01-30'));

    expect($firstDay)->toBe(1000);
    expect($lastDay)->toBe(1000);
    expect($service->isLastSubscriptionDay($subscription, Carbon::parse('2026-01-30')))->toBeTrue();
    expect($service->isLastSubscriptionDay($subscription, Carbon::parse('2026-01-29')))->toBeFalse();
});

it('recognizes lifetime daily revenue equal to the original payment amount', function (): void {
    $service = new RevenueRecognitionService;
    $subscription = new Subscription([
        'starts_at' => Carbon::parse('2026-01-15')->startOfDay(),
        'ends_at' => Carbon::parse('2026-03-14')->endOfDay(),
    ]);
    $payment = new Payment(['amount_minor' => 30000, 'currency' => 'USD']);

    expect($service->lifetimeDailyRecognizedMinor($payment, $subscription))->toBe(30000);
});

it('calculates unused future day refund amount after cancellation day', function (): void {
    $service = new RevenueRecognitionService;
    $subscription = new Subscription([
        'starts_at' => Carbon::parse('2026-01-01')->startOfDay(),
        'ends_at' => Carbon::parse('2026-01-30')->endOfDay(),
    ]);
    $payment = new Payment(['amount_minor' => 30000, 'currency' => 'USD']);

    $refundAmount = $service->unusedFutureDaysAmountMinor(
        $payment,
        $subscription,
        Carbon::parse('2026-01-10'),
    );

    expect($refundAmount)->toBe(20000);
});
