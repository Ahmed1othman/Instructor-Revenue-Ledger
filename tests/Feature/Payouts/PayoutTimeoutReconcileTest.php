<?php

use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Payouts\Actions\CheckPayoutStatusAction;
use App\Domain\Payouts\Actions\CreateInstructorPayoutAction;
use App\Domain\Payouts\Actions\CreatePayoutBatchAction;
use App\Domain\Payouts\Actions\ProcessInstructorPayoutAction;
use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Payouts\Enums\ProviderResultStatus;
use App\Domain\Payouts\Jobs\ProcessInstructorPayoutJob;
use App\Domain\Payouts\Support\PayoutSnapshot;
use App\Models\InstructorLedgerEntry;
use App\Models\Payout;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\SeedsInstructorBalances;

uses(SeedsInstructorBalances::class);

it('marks payout pending confirmation on timeout without creating a debit', function (): void {
    ['balance' => $balance] = $this->seedInstructorWithOutstanding(7000);
    $this->fakePayoutProvider->forceSendResult(ProviderResultStatus::TimeoutUnknown);

    $batch = app(CreatePayoutBatchAction::class)->execute();
    $payout = app(CreateInstructorPayoutAction::class)->execute($batch, $balance);

    ProcessInstructorPayoutJob::dispatchSync($payout->id);

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::PendingConfirmation);
    expect($payout->active_snapshot_key)->not->toBeNull();
    expect(InstructorLedgerEntry::query()->where('type', LedgerEntryType::PayoutDebit)->count())->toBe(0);
});

it('does not call provider send again when processing pending confirmation payout', function (): void {
    ['balance' => $balance] = $this->seedInstructorWithOutstanding(7000);
    $this->fakePayoutProvider->forceSendResult(ProviderResultStatus::TimeoutUnknown);

    $batch = app(CreatePayoutBatchAction::class)->execute();
    $payout = app(CreateInstructorPayoutAction::class)->execute($batch, $balance);

    ProcessInstructorPayoutJob::dispatchSync($payout->id);
    $this->fakePayoutProvider->resetCallCounts();

    app(ProcessInstructorPayoutAction::class)->execute($payout->id);

    expect($this->fakePayoutProvider->sendCallCount)->toBe(0);
});

it('creates exactly one payout debit when reconcile confirms success', function (): void {
    ['balance' => $balance] = $this->seedInstructorWithOutstanding(6500);
    $this->fakePayoutProvider->forceSendResult(ProviderResultStatus::TimeoutUnknown);

    $batch = app(CreatePayoutBatchAction::class)->execute();
    $payout = app(CreateInstructorPayoutAction::class)->execute($batch, $balance);

    ProcessInstructorPayoutJob::dispatchSync($payout->id);

    $this->fakePayoutProvider->forceCheckStatusResult(ProviderResultStatus::Success);

    app(CheckPayoutStatusAction::class)->execute($payout->id);

    expect(
        InstructorLedgerEntry::query()
            ->where('type', LedgerEntryType::PayoutDebit)
            ->count()
    )->toBe(1);

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Succeeded);
    expect($payout->active_snapshot_key)->toBeNull();
});

it('marks payout failed with no debit when reconcile confirms failure', function (): void {
    ['balance' => $balance] = $this->seedInstructorWithOutstanding(6500);
    $this->fakePayoutProvider->forceSendResult(ProviderResultStatus::TimeoutUnknown);

    $batch = app(CreatePayoutBatchAction::class)->execute();
    $payout = app(CreateInstructorPayoutAction::class)->execute($batch, $balance);

    ProcessInstructorPayoutJob::dispatchSync($payout->id);

    $this->fakePayoutProvider->forceCheckStatusResult(ProviderResultStatus::PermanentFailure);

    app(CheckPayoutStatusAction::class)->execute($payout->id);

    expect(InstructorLedgerEntry::query()->where('type', LedgerEntryType::PayoutDebit)->count())->toBe(0);

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Failed);
    expect($payout->active_snapshot_key)->toBeNull();
});

it('prevents duplicate active payouts via active_snapshot_key unique index', function (): void {
    ['balance' => $balance] = $this->seedInstructorWithOutstanding(5000);

    $batch = app(CreatePayoutBatchAction::class)->execute();
    $first = app(CreateInstructorPayoutAction::class)->execute($batch, $balance);
    $second = app(CreateInstructorPayoutAction::class)->execute($batch, $balance->fresh());

    expect($first)->not->toBeNull();
    expect($second)->toBeNull();
    expect(Payout::query()->count())->toBe(1);

    $expectedKey = PayoutSnapshot::activeSnapshotKey(
        $balance->instructor_id,
        $balance->currency,
        PayoutSnapshot::balanceSnapshotHash($balance->fresh()),
    );

    expect($first->active_snapshot_key)->toBe($expectedKey);
});

it('reconciles pending confirmation payouts via payouts reconcile command', function (): void {
    $this->seedInstructorWithOutstanding(4200);
    $this->fakePayoutProvider->forceSendResult(ProviderResultStatus::TimeoutUnknown);

    Artisan::call('payouts:run');

    $this->fakePayoutProvider->forceCheckStatusResult(ProviderResultStatus::Success);

    Artisan::call('payouts:reconcile');

    expect(Payout::query()->first()->status)->toBe(PayoutStatus::Succeeded);
    expect(InstructorLedgerEntry::query()->where('type', LedgerEntryType::PayoutDebit)->count())->toBe(1);
});
