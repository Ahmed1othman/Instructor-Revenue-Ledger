<?php

use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Revenue\Actions\AllocateRevenueForDayAction;
use App\Domain\Revenue\Enums\SubscriptionStatus;
use App\Filament\Resources\SubscriptionResource;
use App\Filament\Resources\SubscriptionResource\Pages\ViewSubscription;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\InstructorLedgerEntry;
use App\Models\LessonConsumption;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Refund;
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

function authenticateSubscriptionFilamentUser(): User
{
    $user = User::factory()->admin()->create();
    test()->actingAs($user);

    return $user;
}

function createFilamentSubscriptionScenario(): array
{
    $student = User::factory()->create(['name' => 'Demo Student']);
    $plan = Plan::factory()->create([
        'name' => 'Spring Plan',
        'price_minor' => 30000,
        'instructor_share_bps' => 6000,
        'duration_days' => 60,
    ]);

    $subscription = Subscription::factory()->create([
        'user_id' => $student->id,
        'plan_id' => $plan->id,
        'starts_at' => '2026-01-15 00:00:00',
        'ends_at' => '2026-03-15 23:59:59',
        'currency' => 'USD',
    ]);

    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => 30000,
        'currency' => 'USD',
    ]);

    $instructor = Instructor::factory()->create();
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);

    foreach (range(15, 25) as $day) {
        LessonConsumption::factory()->create([
            'subscription_id' => $subscription->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'instructor_id' => $instructor->id,
            'valid_watched_seconds' => 3600,
            'consumed_at' => sprintf('2026-01-%02d 10:00:00', $day),
        ]);
    }

    return compact('subscription', 'student', 'plan', 'instructor');
}

it('allows authenticated admin to access subscription list and view', function (): void {
    authenticateSubscriptionFilamentUser();
    ['subscription' => $subscription] = createFilamentSubscriptionScenario();

    $this->get(SubscriptionResource::getUrl('index'))->assertOk();
    $this->get(SubscriptionResource::getUrl('view', ['record' => $subscription]))->assertOk();
});

it('displays subscription and financial summary fields on the view page', function (): void {
    authenticateSubscriptionFilamentUser();
    ['subscription' => $subscription, 'student' => $student, 'plan' => $plan] = createFilamentSubscriptionScenario();

    app(AllocateRevenueForDayAction::class)->execute(Carbon::parse('2026-01-20'));

    $response = $this->get(SubscriptionResource::getUrl('view', ['record' => $subscription]));

    $response->assertOk();
    $response->assertSee($student->name);
    $response->assertSee($plan->name);
    $response->assertSee('Payment lifecycle');
    $response->assertSee('Revenue split');
    $response->assertSee('Financial lifecycle');
    $response->assertSee('Financial Summary');
    $response->assertSee('300.00 USD');
    $response->assertSee('Original payment');
    $response->assertSee('Earned revenue split');
    $response->assertSee('Earned amount');
    $response->assertSee('Unallocated instructor pool');
    $response->assertSee('Total platform retained');
    $response->assertSee('Instructor outstanding');
});

it('exposes Refund Unused Days action on the view page only when refundable', function (): void {
    $user = authenticateSubscriptionFilamentUser();
    ['subscription' => $subscription] = createFilamentSubscriptionScenario();

    $listResponse = $this->get(SubscriptionResource::getUrl('index'));
    $listResponse->assertOk();
    $listResponse->assertDontSee('Refund Unused Days');

    Livewire::actingAs($user)
        ->test(ViewSubscription::class, ['record' => $subscription->getRouteKey()])
        ->assertActionExists('refundUnusedDays');
});

it('hides refund action when subscription period has ended', function (): void {
    $user = authenticateSubscriptionFilamentUser();
    $student = User::factory()->create();
    $plan = Plan::factory()->create(['duration_days' => 30, 'price_minor' => 30000]);

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

    Livewire::actingAs($user)
        ->test(ViewSubscription::class, ['record' => $subscription->getRouteKey()])
        ->assertActionHidden('refundUnusedDays');
});

it('processes refund via Refund Unused Days action using today without ledger reversals', function (): void {
    $user = authenticateSubscriptionFilamentUser();
    ['subscription' => $subscription] = createFilamentSubscriptionScenario();

    Livewire::actingAs($user)
        ->test(ViewSubscription::class, ['record' => $subscription->getRouteKey()])
        ->callAction('refundUnusedDays');

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Refunded);
    expect($subscription->cancelled_at->toDateString())->toBe('2026-02-01');
    expect(Refund::query()->where('subscription_id', $subscription->id)->count())->toBe(1);
    expect(InstructorLedgerEntry::query()->where('type', LedgerEntryType::EarningCredit)->count())->toBeGreaterThan(0);
    expect(InstructorLedgerEntry::query()->where('type', 'earning_reversal')->count())->toBe(0);
    expect(InstructorLedgerEntry::query()->where('type', 'clawback')->count())->toBe(0);
});

it('hides refund action after refund and keeps a single refund record', function (): void {
    $user = authenticateSubscriptionFilamentUser();
    ['subscription' => $subscription] = createFilamentSubscriptionScenario();

    $component = Livewire::actingAs($user)
        ->test(ViewSubscription::class, ['record' => $subscription->getRouteKey()]);

    $component->callAction('refundUnusedDays');

    expect(Refund::query()->where('subscription_id', $subscription->id)->count())->toBe(1);

    $component->assertActionHidden('refundUnusedDays');
});

it('disables create edit and delete on subscription resource', function (): void {
    authenticateSubscriptionFilamentUser();
    ['subscription' => $subscription] = createFilamentSubscriptionScenario();

    expect(SubscriptionResource::canCreate())->toBeFalse();
    expect(SubscriptionResource::canEdit($subscription))->toBeFalse();
    expect(SubscriptionResource::canDelete($subscription))->toBeFalse();
    expect(SubscriptionResource::canDeleteAny())->toBeFalse();
    expect(SubscriptionResource::hasPage('create'))->toBeFalse();
    expect(SubscriptionResource::hasPage('edit'))->toBeFalse();
});
