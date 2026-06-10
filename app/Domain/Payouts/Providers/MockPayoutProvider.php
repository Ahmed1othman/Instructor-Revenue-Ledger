<?php

namespace App\Domain\Payouts\Providers;

use App\Domain\Payouts\Contracts\PayoutProvider;
use App\Domain\Payouts\DTOs\PayoutProviderResult;
use App\Domain\Payouts\Enums\ProviderResultStatus;
use App\Models\Payout;

class MockPayoutProvider implements PayoutProvider
{
    /** @var array<int, ProviderResultStatus> */
    private array $delayedSuccessPayouts = [];

    public function send(Payout $payout): PayoutProviderResult
    {
        $roll = random_int(1, 3);

        return match ($roll) {
            1 => new PayoutProviderResult(
                status: ProviderResultStatus::Success,
                providerReference: 'mock-success-'.$payout->id,
            ),
            2 => new PayoutProviderResult(
                status: ProviderResultStatus::PermanentFailure,
                message: 'mock permanent failure',
            ),
            default => $this->timeoutAfterPossibleSuccess($payout),
        };
    }

    public function checkStatus(Payout $payout): PayoutProviderResult
    {
        if (isset($this->delayedSuccessPayouts[$payout->id])) {
            return new PayoutProviderResult(
                status: $this->delayedSuccessPayouts[$payout->id],
                providerReference: 'mock-check-'.$payout->id,
            );
        }

        return new PayoutProviderResult(
            status: ProviderResultStatus::Success,
            providerReference: 'mock-check-'.$payout->id,
        );
    }

    private function timeoutAfterPossibleSuccess(Payout $payout): PayoutProviderResult
    {
        if (random_int(0, 1) === 1) {
            $this->delayedSuccessPayouts[$payout->id] = ProviderResultStatus::Success;
        } else {
            $this->delayedSuccessPayouts[$payout->id] = ProviderResultStatus::PermanentFailure;
        }

        return new PayoutProviderResult(
            status: ProviderResultStatus::TimeoutUnknown,
            message: 'mock timeout — outcome unknown until status check',
        );
    }
}
