<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Payouts\Contracts\PayoutProvider;
use App\Domain\Payouts\Enums\PayoutStatus;
use App\Domain\Payouts\Enums\ProviderResultStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

class CheckPayoutStatusAction
{
    public function __construct(
        private readonly PayoutProvider $provider,
        private readonly RecordPayoutAttemptAction $recordAttempt,
        private readonly MarkPayoutSucceededAction $markSucceeded,
        private readonly MarkPayoutFailedAction $markFailed,
    ) {}

    public function execute(int $payoutId): void
    {
        $payout = Payout::query()->find($payoutId);

        if ($payout === null || $payout->status !== PayoutStatus::PendingConfirmation) {
            return;
        }

        $result = $this->provider->checkStatus($payout);

        DB::transaction(function () use ($payout, $result): void {
            $fresh = Payout::query()->lockForUpdate()->find($payout->id);

            if ($fresh === null || $fresh->status !== PayoutStatus::PendingConfirmation) {
                return;
            }

            $this->recordAttempt->execute($fresh, 'status_check', $result);

            match ($result->status) {
                ProviderResultStatus::Success => $this->markSucceeded->execute($fresh, $result),
                ProviderResultStatus::PermanentFailure => $this->markFailed->execute($fresh),
                ProviderResultStatus::TimeoutUnknown => null,
            };
        });
    }
}
