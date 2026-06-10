<?php

use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Payouts\Actions\CreateInstructorPayoutAction;
use App\Domain\Payouts\Actions\CreatePayoutBatchAction;
use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Payouts\Enums\ProviderResultStatus;
use App\Domain\Payouts\Jobs\ProcessInstructorPayoutJob;
use App\Models\InstructorLedgerEntry;
use App\Models\Payout;
use Tests\Support\SeedsInstructorBalances;

uses(SeedsInstructorBalances::class);

it('does not create duplicate payout debit when payout job is retried', function (): void {
    ['balance' => $balance] = $this->seedInstructorWithOutstanding(9500);
    $this->fakePayoutProvider->forceSendResult(ProviderResultStatus::Success);

    $batch = app(CreatePayoutBatchAction::class)->execute();
    $payout = app(CreateInstructorPayoutAction::class)->execute($batch, $balance);

    expect($payout)->not->toBeNull();

    ProcessInstructorPayoutJob::dispatchSync($payout->id);
    ProcessInstructorPayoutJob::dispatchSync($payout->id);

    expect(
        InstructorLedgerEntry::query()
            ->where('type', LedgerEntryType::PayoutDebit)
            ->count()
    )->toBe(1);

    expect(Payout::query()->find($payout->id)->status)->toBe(PayoutStatus::Succeeded);
});
