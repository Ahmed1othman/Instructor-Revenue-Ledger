<?php

namespace App\Domain\Revenue\DTOs;

final class SubscriptionFinancialSummary
{
    public function __construct(
        public readonly int $paidMinor,
        public readonly int $earnedMinor,
        public readonly int $unearnedMinor,
        public readonly int $refundedMinor,
        public readonly int $remainingRefundableMinor,
        public readonly int $platformContractualShareMinor,
        public readonly int $instructorPoolMinor,
        public readonly int $instructorPoolAllocatedMinor,
        public readonly int $unallocatedInstructorPoolMinor,
        public readonly int $totalPlatformRetainedMinor,
        public readonly int $instructorPaidMinor,
        public readonly int $instructorOutstandingMinor,
        public readonly string $currency,
    ) {}

    /** @deprecated Use totalPlatformRetainedMinor */
    public function platformEarnedMinor(): int
    {
        return $this->totalPlatformRetainedMinor;
    }
}
