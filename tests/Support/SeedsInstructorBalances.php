<?php

namespace Tests\Support;

use App\Domain\Ledger\Actions\RecordInstructorLedgerEntryAction;
use App\Domain\Ledger\Actions\UpdateInstructorBalanceProjectionAction;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Models\Instructor;
use App\Models\InstructorBalance;
use Illuminate\Support\Str;

trait SeedsInstructorBalances
{
    protected function seedInstructorWithOutstanding(int $amountMinor = 15000, string $currency = 'USD'): array
    {
        $instructor = Instructor::factory()->create();
        $record = app(RecordInstructorLedgerEntryAction::class);
        $update = app(UpdateInstructorBalanceProjectionAction::class);

        $entry = $record->execute(
            instructorId: $instructor->id,
            type: LedgerEntryType::EarningCredit,
            direction: LedgerDirection::Credit,
            amountMinor: $amountMinor,
            currency: $currency,
            idempotencyKey: 'ledger:seed:'.Str::uuid(),
        );

        $update->execute($entry);

        $balance = InstructorBalance::query()
            ->where('instructor_id', $instructor->id)
            ->where('currency', $currency)
            ->firstOrFail();

        return [
            'instructor' => $instructor,
            'balance' => $balance,
        ];
    }
}
