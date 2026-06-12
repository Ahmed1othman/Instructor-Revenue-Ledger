<?php

namespace App\Domain\Revenue\Actions;

use App\Domain\Ledger\Actions\RecordInstructorLedgerEntryAction;
use App\Domain\Ledger\Actions\UpdateInstructorBalanceProjectionAction;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\SettlementGranularity;
use App\Domain\Revenue\Enums\SettlementPeriodStatus;
use App\Domain\Revenue\Services\AllocationModeGuardService;
use App\Domain\Revenue\Services\RevenueAllocationService;
use App\Domain\Revenue\Services\RevenueRecognitionService;
use App\Models\Payment;
use App\Models\RevenueAllocation;
use App\Models\SettlementPeriod;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AllocateRevenueForDayAction
{
    public function __construct(
        private readonly AllocationModeGuardService $modeGuard,
        private readonly RevenueRecognitionService $recognitionService,
        private readonly RevenueAllocationService $allocationService,
        private readonly RecordInstructorLedgerEntryAction $recordLedgerEntry,
        private readonly UpdateInstructorBalanceProjectionAction $updateBalance,
    ) {}

    public function execute(Carbon $date): SettlementPeriod
    {
        $day = $date->copy()->startOfDay();

        if ($day->greaterThanOrEqualTo(now()->startOfDay())) {
            throw new InvalidArgumentException(
                sprintf('Cannot allocate revenue for future or current day: %s.', $day->toDateString()),
            );
        }

        $this->modeGuard->assertDailyAllocationAllowed($day);

        $period = SettlementPeriod::query()->firstOrCreate(
            [
                'granularity' => SettlementGranularity::Daily,
                'period_start' => $day->toDateString(),
            ],
            [
                'year' => (int) $day->year,
                'month' => (int) $day->month,
                'period_end' => $day->toDateString(),
                'status' => SettlementPeriodStatus::Open,
            ],
        );

        DB::transaction(function () use ($period): void {
            $period->update(['status' => SettlementPeriodStatus::Allocating]);
        });

        // Subscription status (active, refunded, cancelled, etc.) is intentionally ignored.
        // A refunded/cancelled subscription remains allocatable on any elapsed day within its
        // access window — including the cancellation day once that calendar day has ended.
        $payments = Payment::query()
            ->with(['subscription.plan'])
            ->where('status', PaymentStatus::Succeeded)
            ->whereHas('subscription', function ($query) use ($day): void {
                $query->where('starts_at', '<=', $day->copy()->endOfDay())
                    ->where('ends_at', '>=', $day->copy()->startOfDay());
            })
            ->get();

        foreach ($payments as $payment) {
            $this->allocatePaymentForDay($payment, $period, $day);
        }

        $period->update(['status' => SettlementPeriodStatus::Allocated]);

        return $period;
    }

    private function allocatePaymentForDay(Payment $payment, SettlementPeriod $period, Carbon $day): void
    {
        $subscription = $payment->subscription;
        $plan = $subscription->plan;

        $earnedMinor = $this->recognitionService->earnedAmountMinorForDay($payment, $subscription, $day);

        if ($earnedMinor === 0) {
            return;
        }

        $instructorPoolMinor = $this->recognitionService->instructorPoolMinor(
            $earnedMinor,
            $plan->instructor_share_bps,
        );

        if ($instructorPoolMinor === 0) {
            return;
        }

        $weights = $this->allocationService->engagementWeightsForDay($subscription, $day);

        if ($this->allocationService->totalEngagementWeight($weights) === 0) {
            Log::info('Unallocated instructor pool — no engagement', [
                'allocation_date' => $day->toDateString(),
                'subscription_id' => $subscription->id,
                'instructor_pool_minor' => $instructorPoolMinor,
            ]);

            return;
        }

        $allocations = $this->allocationService->allocatePool($instructorPoolMinor, $weights);

        foreach ($allocations as $instructorId => $amountMinor) {
            if ($amountMinor === 0) {
                continue;
            }

            $this->persistAllocation(
                $period,
                $subscription,
                $day,
                (int) $instructorId,
                $instructorPoolMinor,
                $weights[(int) $instructorId],
                $amountMinor,
                $payment->currency,
            );
        }
    }

    private function persistAllocation(
        SettlementPeriod $period,
        Subscription $subscription,
        Carbon $day,
        int $instructorId,
        int $instructorPoolMinor,
        int $engagementWeight,
        int $amountMinor,
        string $currency,
    ): void {
        DB::transaction(function () use (
            $period,
            $subscription,
            $day,
            $instructorId,
            $instructorPoolMinor,
            $engagementWeight,
            $amountMinor,
            $currency,
        ): void {
            $allocationKey = sprintf(
                'allocation:daily:%s:%d:%d',
                $day->toDateString(),
                $subscription->id,
                $instructorId,
            );

            RevenueAllocation::query()->firstOrCreate(
                ['idempotency_key' => $allocationKey],
                [
                    'settlement_period_id' => $period->id,
                    'allocation_date' => $day->toDateString(),
                    'subscription_id' => $subscription->id,
                    'instructor_id' => $instructorId,
                    'instructor_pool_minor' => $instructorPoolMinor,
                    'engagement_weight' => $engagementWeight,
                    'allocated_amount_minor' => $amountMinor,
                    'currency' => $currency,
                ],
            );

            $ledgerKey = sprintf(
                'ledger:earning:daily:%s:%d:%d',
                $day->toDateString(),
                $subscription->id,
                $instructorId,
            );

            $entry = $this->recordLedgerEntry->execute(
                instructorId: $instructorId,
                type: LedgerEntryType::EarningCredit,
                direction: LedgerDirection::Credit,
                amountMinor: $amountMinor,
                currency: $currency,
                idempotencyKey: $ledgerKey,
                subscriptionId: $subscription->id,
                settlementPeriodId: $period->id,
            );

            if ($entry->wasRecentlyCreated) {
                $this->updateBalance->execute($entry);
            }
        });
    }
}
