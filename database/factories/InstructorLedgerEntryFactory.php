<?php

namespace Database\Factories;

use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Models\Instructor;
use App\Models\InstructorLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InstructorLedgerEntry>
 */
class InstructorLedgerEntryFactory extends Factory
{
    protected $model = InstructorLedgerEntry::class;

    public function definition(): array
    {
        return [
            'instructor_id' => Instructor::factory(),
            'subscription_id' => null,
            'settlement_period_id' => null,
            'payout_id' => null,
            'type' => LedgerEntryType::EarningCredit,
            'direction' => LedgerDirection::Credit,
            'amount_minor' => 1000,
            'currency' => 'USD',
            'idempotency_key' => 'ledger:'.Str::uuid(),
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }
}
