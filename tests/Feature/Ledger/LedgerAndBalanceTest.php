<?php

use App\Domain\Ledger\Actions\RecordInstructorLedgerEntryAction;
use App\Domain\Ledger\Actions\UpdateInstructorBalanceProjectionAction;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Models\Instructor;
use App\Models\InstructorLedgerEntry;
use Tests\Support\RebuildsInstructorBalances;

uses(RebuildsInstructorBalances::class);

it('creates earning credit ledger entries idempotently', function (): void {
    $instructor = Instructor::factory()->create();
    $action = app(RecordInstructorLedgerEntryAction::class);

    $first = $action->execute(
        instructorId: $instructor->id,
        type: LedgerEntryType::EarningCredit,
        direction: LedgerDirection::Credit,
        amountMinor: 5000,
        currency: 'USD',
        idempotencyKey: 'ledger:test:1',
    );

    $second = $action->execute(
        instructorId: $instructor->id,
        type: LedgerEntryType::EarningCredit,
        direction: LedgerDirection::Credit,
        amountMinor: 5000,
        currency: 'USD',
        idempotencyKey: 'ledger:test:1',
    );

    expect($second->id)->toBe($first->id);
    expect(InstructorLedgerEntry::query()->count())->toBe(1);
});

it('updates instructor balance projection from ledger entries', function (): void {
    $instructor = Instructor::factory()->create();
    $record = app(RecordInstructorLedgerEntryAction::class);
    $update = app(UpdateInstructorBalanceProjectionAction::class);

    $entry = $record->execute(
        instructorId: $instructor->id,
        type: LedgerEntryType::EarningCredit,
        direction: LedgerDirection::Credit,
        amountMinor: 7500,
        currency: 'USD',
        idempotencyKey: 'ledger:test:2',
    );

    $update->execute($entry);

    $this->assertBalanceMatchesLedger($instructor);
});
