<?php

use App\Domain\Revenue\Actions\AllocateRevenueForDayAction;
use App\Filament\Widgets\FinancialOverviewStats;
use App\Filament\Widgets\PayoutPipelineStats;
use App\Filament\Widgets\RevenueSplitStats;
use App\Filament\Widgets\SubscriptionStatusStats;
use App\Filament\Widgets\TopInstructorsByEarned;
use App\Filament\Widgets\TopInstructorsByOutstanding;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\LessonConsumption;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-01 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function seedDashboardFinancialData(): array
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

    $instructor = Instructor::factory()->create(['name' => 'Widget Instructor']);
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);

    LessonConsumption::factory()->create([
        'subscription_id' => $subscription->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
        'valid_watched_seconds' => 3600,
        'consumed_at' => '2026-01-04 10:00:00',
    ]);

    app(AllocateRevenueForDayAction::class)->execute(Carbon::parse('2026-01-04'));

    return compact('subscription', 'instructor');
}

it('shows platform payment and allocation totals on financial overview widgets', function (): void {
    seedDashboardFinancialData();

    Livewire::test(FinancialOverviewStats::class)
        ->assertSee('Total payments')
        ->assertSee('300.00');

    Livewire::test(RevenueSplitStats::class)
        ->assertSee('Instructor allocated')
        ->assertSee('6.00');
});

it('shows subscription and payout pipeline counts on dashboard widgets', function (): void {
    seedDashboardFinancialData();

    Livewire::test(SubscriptionStatusStats::class)
        ->assertSee('Active subscriptions')
        ->assertSee('1');

    Livewire::test(PayoutPipelineStats::class)
        ->assertSee('Pending payouts')
        ->assertSee('0');
});

it('lists top instructors by earned and outstanding on table widgets', function (): void {
    ['instructor' => $instructor] = seedDashboardFinancialData();

    Livewire::test(TopInstructorsByEarned::class)
        ->assertSee($instructor->name)
        ->assertSee('6.00');

    Livewire::test(TopInstructorsByOutstanding::class)
        ->assertSee($instructor->name)
        ->assertSee('6.00');
});
