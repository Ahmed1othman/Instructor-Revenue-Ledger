<?php

namespace App\Domain\Revenue\Actions;

use App\Domain\Ledger\Actions\RecordInstructorLedgerEntryAction;
use App\Domain\Ledger\Actions\UpdateInstructorBalanceProjectionAction;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Revenue\Enums\PaymentStatus;
use App\Domain\Revenue\Enums\SettlementPeriodStatus;
use App\Domain\Revenue\Services\RevenueAllocationService;
use App\Domain\Revenue\Services\RevenueRecognitionService;
use App\Models\Payment;
use App\Models\RevenueAllocation;
use App\Models\SettlementPeriod;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AllocateRevenueForSettlementAction
{
    public function __construct(
        private readonly RevenueRecognitionService $recognitionService,
        private readonly RevenueAllocationService $allocationService,
        private readonly RecordInstructorLedgerEntryAction $recordLedgerEntry,
        private readonly UpdateInstructorBalanceProjectionAction $updateBalance,
    ) {}

    public function execute(SettlementPeriod $period): void
    {
        DB::transaction(function () use ($period): void {
            $period->update(['status' => SettlementPeriodStatus::Allocating]);
        });

        $payments = Payment::query()
            ->with(['subscription.plan'])
            ->where('status', PaymentStatus::Succeeded)
            ->whereHas('subscription', function ($query) use ($period): void {
                $query->where('starts_at', '<=', $period->period_end->endOfDay())
                    ->where('ends_at', '>=', $period->period_start->startOfDay());
            })
            ->get();

        foreach ($payments as $payment) {
            $this->allocatePaymentForPeriod($payment, $period);
        }

        $period->update(['status' => SettlementPeriodStatus::Allocated]);
    }

    private function allocatePaymentForPeriod(Payment $payment, SettlementPeriod $period): void
    {
        $subscription = $payment->subscription;
        $plan = $subscription->plan;

        $earnedMinor = $this->recognitionService->earnedAmountMinor($payment, $subscription, $period);

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

        $weights = $this->allocationService->engagementWeights($subscription, $period);

        if ($this->allocationService->totalEngagementWeight($weights) === 0) {
            Log::info('Unallocated instructor pool — no engagement', [
                'settlement_period_id' => $period->id,
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
        int $instructorId,
        int $instructorPoolMinor,
        int $engagementWeight,
        int $amountMinor,
        string $currency,
    ): void {
        DB::transaction(function () use (
            $period,
            $subscription,
            $instructorId,
            $instructorPoolMinor,
            $engagementWeight,
            $amountMinor,
            $currency,
        ): void {
            $allocationKey = sprintf(
                'allocation:%d:%d:%d',
                $period->id,
                $subscription->id,
                $instructorId,
            );

            RevenueAllocation::query()->firstOrCreate(
                ['idempotency_key' => $allocationKey],
                [
                    'settlement_period_id' => $period->id,
                    'subscription_id' => $subscription->id,
                    'instructor_id' => $instructorId,
                    'instructor_pool_minor' => $instructorPoolMinor,
                    'engagement_weight' => $engagementWeight,
                    'allocated_amount_minor' => $amountMinor,
                    'currency' => $currency,
                ],
            );

            $ledgerKey = sprintf(
                'ledger:earning:%d:%d:%d',
                $period->id,
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
