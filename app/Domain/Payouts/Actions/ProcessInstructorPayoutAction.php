<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Payouts\Contracts\PayoutProvider;
use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Payouts\Enums\ProviderResultStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

class ProcessInstructorPayoutAction
{
    public function __construct(
        private readonly PayoutProvider $provider,
        private readonly RecordPayoutAttemptAction $recordAttempt,
        private readonly MarkPayoutSucceededAction $markSucceeded,
        private readonly MarkPayoutFailedAction $markFailed,
        private readonly MarkPayoutPendingConfirmationAction $markPendingConfirmation,
    ) {}

    public function execute(int $payoutId): void
    {
        $payout = Payout::query()->find($payoutId);

        if ($payout === null) {
            return;
        }

        if ($this->shouldSkipProcessing($payout->status)) {
            return;
        }

        if ($payout->status === PayoutStatus::Pending) {
            $movedToProcessing = $this->markProcessing($payout->id);

            if (! $movedToProcessing) {
                return;
            }

            $payout->refresh();
        }

        if ($payout->status !== PayoutStatus::Processing) {
            return;
        }

        $result = $this->provider->send($payout);

        DB::transaction(function () use ($payout, $result): void {
            $fresh = Payout::query()->lockForUpdate()->find($payout->id);

            if ($fresh === null || $this->shouldSkipProcessing($fresh->status)) {
                return;
            }

            $this->recordAttempt->execute($fresh, 'send', $result);

            match ($result->status) {
                ProviderResultStatus::Success => $this->markSucceeded->execute($fresh, $result),
                ProviderResultStatus::PermanentFailure => $this->markFailed->execute($fresh),
                ProviderResultStatus::TimeoutUnknown => $this->markPendingConfirmation->execute($fresh),
            };
        });
    }

    private function shouldSkipProcessing(PayoutStatus $status): bool
    {
        return in_array($status, [
            PayoutStatus::Succeeded,
            PayoutStatus::Failed,
            PayoutStatus::PendingConfirmation,
        ], true);
    }

    private function markProcessing(int $payoutId): bool
    {
        return DB::transaction(function () use ($payoutId): bool {
            $payout = Payout::query()->lockForUpdate()->find($payoutId);

            if ($payout === null || $payout->status !== PayoutStatus::Pending) {
                return false;
            }

            $payout->update(['status' => PayoutStatus::Processing]);

            return true;
        });
    }
}
