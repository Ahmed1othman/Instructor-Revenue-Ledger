<?php

namespace Tests\Support;

use App\Domain\Ledger\Enums\LedgerDirection;
use App\Models\Instructor;
use App\Models\InstructorBalance;
use App\Models\InstructorLedgerEntry;

trait RebuildsInstructorBalances
{
    protected function rebuildBalanceFromLedger(Instructor $instructor, string $currency = 'USD'): array
    {
        $entries = InstructorLedgerEntry::query()
            ->where('instructor_id', $instructor->id)
            ->where('currency', $currency)
            ->orderBy('id')
            ->get();

        $totalEarned = 0;
        $totalPaid = 0;

        foreach ($entries as $entry) {
            if ($entry->direction === LedgerDirection::Credit) {
                $totalEarned += $entry->amount_minor;
            } else {
                $totalPaid += $entry->amount_minor;
            }
        }

        return [
            'total_earned_minor' => $totalEarned,
            'total_paid_minor' => $totalPaid,
            'outstanding_minor' => $totalEarned - $totalPaid,
            'last_ledger_entry_id' => $entries->last()?->id,
        ];
    }

    protected function assertBalanceMatchesLedger(Instructor $instructor, string $currency = 'USD'): void
    {
        $expected = $this->rebuildBalanceFromLedger($instructor, $currency);

        $balance = InstructorBalance::query()
            ->where('instructor_id', $instructor->id)
            ->where('currency', $currency)
            ->first();

        expect($balance)->not->toBeNull();
        expect($balance->total_earned_minor)->toBe($expected['total_earned_minor']);
        expect($balance->total_paid_minor)->toBe($expected['total_paid_minor']);
        expect($balance->outstanding_minor)->toBe($expected['outstanding_minor']);
        expect($balance->last_ledger_entry_id)->toBe($expected['last_ledger_entry_id']);
    }
}
