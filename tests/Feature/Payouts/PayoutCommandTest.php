<?php

use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Payouts\Enums\ProviderResultStatus;
use App\Models\InstructorLedgerEntry;
use App\Models\Payout;
use App\Models\PayoutBatch;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\SeedsInstructorBalances;

uses(SeedsInstructorBalances::class);

it('creates payout batch and payouts for outstanding balances', function (): void {
    $this->seedInstructorWithOutstanding(12000);
    $this->fakePayoutProvider->forceSendResult(ProviderResultStatus::Success);

    Artisan::call('payouts:run');

    expect(PayoutBatch::query()->count())->toBe(1);
    expect(Payout::query()->count())->toBe(1);
    expect(Payout::query()->first()->amount_minor)->toBe(12000);
});

it('does not create duplicate active payouts when payouts run executes twice', function (): void {
    $this->seedInstructorWithOutstanding(10000);
    $this->fakePayoutProvider->forceSendResult(ProviderResultStatus::TimeoutUnknown);

    Artisan::call('payouts:run');
    Artisan::call('payouts:run');

    expect(Payout::query()->count())->toBe(1);
    expect(Payout::query()->first()->status)->toBe(PayoutStatus::PendingConfirmation);
    expect(Payout::query()->first()->active_snapshot_key)->not->toBeNull();
});

it('creates exactly one payout debit ledger entry on provider success', function (): void {
    $this->seedInstructorWithOutstanding(8000);
    $this->fakePayoutProvider->forceSendResult(ProviderResultStatus::Success);

    Artisan::call('payouts:run');

    expect(
        InstructorLedgerEntry::query()
            ->where('type', LedgerEntryType::PayoutDebit)
            ->count()
    )->toBe(1);

    expect(Payout::query()->first()->status)->toBe(PayoutStatus::Succeeded);
    expect(Payout::query()->first()->active_snapshot_key)->toBeNull();
});
