<?php

use App\Domain\Revenue\Actions\AllocateRevenueForDayAction;
use App\Filament\Widgets\FinancialOverviewStats;
use App\Filament\Widgets\InstructorPoolProgressWidget;
use App\Filament\Widgets\MonthlyFinancialTrendChart;
use App\Filament\Widgets\PayoutPipelineChart;
use App\Filament\Widgets\PayoutPipelineStats;
use App\Filament\Widgets\RecentFinancialActivityWidget;
use App\Filament\Widgets\RecentRefundsWidget;
use App\Filament\Widgets\RevenueCompositionChart;
use App\Filament\Widgets\RevenuePaymentProgressWidget;
use App\Filament\Widgets\RevenueSplitChart;
use App\Filament\Widgets\RevenueSplitStats;
use App\Filament\Widgets\SubscriptionStatusChart;
use App\Filament\Widgets\SubscriptionStatusStats;
use App\Filament\Widgets\TopInstructorsByEarned;
use App\Filament\Widgets\TopInstructorsByOutstanding;
use App\Filament\Widgets\TopSubscriptionsByPaymentWidget;
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
        ->assertSee('Total student payments')
        ->assertSee('300.00 USD');

    Livewire::test(RevenueSplitStats::class)
        ->assertSee('Instructor allocated')
        ->assertSee('Unallocated instructor pool')
        ->assertSee('Total platform retained')
        ->assertSee('6.00');
});

it('shows subscription and payout pipeline counts on dashboard widgets', function (): void {
    seedDashboardFinancialData();

    Livewire::test(SubscriptionStatusStats::class)
        ->assertSee('Active')
        ->assertSee('1');

    Livewire::test(PayoutPipelineStats::class)
        ->assertSee('Pending payouts')
        ->assertSee('Instructor outstanding')
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

it('renders recent refunds table widget', function (): void {
    seedDashboardFinancialData();

    Livewire::test(RecentRefundsWidget::class)
        ->assertSee('Recent refunds');
});

it('renders dashboard chart and progress widgets without error', function (): void {
    seedDashboardFinancialData();

    Livewire::test(RevenueCompositionChart::class)->assertSuccessful();
    Livewire::test(RevenuePaymentProgressWidget::class)
        ->assertSee('Payment utilization')
        ->assertSee('Earned');
    Livewire::test(RevenueSplitChart::class)->assertSuccessful();
    Livewire::test(InstructorPoolProgressWidget::class)
        ->assertSee('Instructor pool utilization');
    Livewire::test(PayoutPipelineChart::class)->assertSuccessful();
    Livewire::test(SubscriptionStatusChart::class)->assertSuccessful();
    Livewire::test(MonthlyFinancialTrendChart::class)->assertSuccessful();
    Livewire::test(TopSubscriptionsByPaymentWidget::class)
        ->assertSee('Top subscriptions by payment');
    Livewire::test(RecentFinancialActivityWidget::class)
        ->assertSee('Recent financial activity');
});
