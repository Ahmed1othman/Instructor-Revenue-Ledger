<?php

use App\Domain\Payouts\Enums\ProviderResultStatus;
use App\Filament\Resources\InstructorBalanceResource;
use App\Filament\Resources\InstructorBalanceResource\Pages\ViewInstructorBalance;
use App\Filament\Resources\InstructorBalanceResource\RelationManagers\PayoutsRelationManager;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Support\SeedsInstructorBalances;

uses(SeedsInstructorBalances::class);

function authenticateFilamentUser(): User
{
    $user = User::factory()->admin()->create();

    test()->actingAs($user);

    return $user;
}

it('allows authenticated admin to access instructor balance list', function (): void {
    authenticateFilamentUser();
    $this->seedInstructorWithOutstanding(10800, 'EGP');

    $response = $this->get(InstructorBalanceResource::getUrl('index'));

    $response->assertOk();
});

it('displays earned paid outstanding and currency on the list page', function (): void {
    authenticateFilamentUser();
    $seeded = $this->seedInstructorWithOutstanding(10800, 'EGP');

    $response = $this->get(InstructorBalanceResource::getUrl('index'));

    $response->assertOk();
    $response->assertSee($seeded['instructor']->name);
    $response->assertSee('108.00 EGP');
    $response->assertSee('EGP');
});

it('displays payout history on the view page', function (): void {
    $user = authenticateFilamentUser();
    $seeded = $this->seedInstructorWithOutstanding(5000, 'USD');
    $this->fakePayoutProvider->forceSendResult(ProviderResultStatus::Success);

    Artisan::call('payouts:run');

    $payout = Payout::query()->firstOrFail();

    $this->get(InstructorBalanceResource::getUrl('view', ['record' => $seeded['balance']]))
        ->assertOk()
        ->assertSee('Payout History');

    Livewire::actingAs($user)
        ->test(PayoutsRelationManager::class, [
            'ownerRecord' => $seeded['balance'],
            'pageClass' => ViewInstructorBalance::class,
        ])
        ->assertCanSeeTableRecords([$payout])
        ->assertSee('50.00 USD')
        ->assertSee($payout->status->value);
});

it('does not register create or edit pages', function (): void {
    expect(InstructorBalanceResource::hasPage('create'))->toBeFalse();
    expect(InstructorBalanceResource::hasPage('edit'))->toBeFalse();
    expect(InstructorBalanceResource::hasPage('index'))->toBeTrue();
    expect(InstructorBalanceResource::hasPage('view'))->toBeTrue();
});

it('disables create edit and delete capabilities on the resource', function (): void {
    authenticateFilamentUser();
    $seeded = $this->seedInstructorWithOutstanding();

    expect(InstructorBalanceResource::canCreate())->toBeFalse();
    expect(InstructorBalanceResource::canEdit($seeded['balance']))->toBeFalse();
    expect(InstructorBalanceResource::canDelete($seeded['balance']))->toBeFalse();
    expect(InstructorBalanceResource::canDeleteAny())->toBeFalse();
});

it('returns not found for create and edit routes', function (): void {
    authenticateFilamentUser();

    $this->get('/admin/instructor-balances/create')->assertNotFound();
    $this->get('/admin/instructor-balances/1/edit')->assertNotFound();
});

it('does not expose payout trigger actions on list or view pages', function (): void {
    authenticateFilamentUser();
    $seeded = $this->seedInstructorWithOutstanding();

    $listResponse = $this->get(InstructorBalanceResource::getUrl('index'));
    $viewResponse = $this->get(InstructorBalanceResource::getUrl('view', ['record' => $seeded['balance']]));

    $listResponse->assertOk();
    $viewResponse->assertOk();

    $listResponse->assertDontSee('Run Payout', false);
    $listResponse->assertDontSee('Trigger Payout', false);
    $listResponse->assertDontSee('Process Payout', false);
    $viewResponse->assertDontSee('Run Payout', false);
    $viewResponse->assertDontSee('Trigger Payout', false);
    $viewResponse->assertDontSee('Process Payout', false);
});
