<?php

namespace App\Domain\Payouts\Providers;

use App\Domain\Payouts\Contracts\PayoutProvider;
use App\Domain\Payouts\DTOs\PayoutProviderResult;
use App\Domain\Payouts\Enums\ProviderResultStatus;
use App\Models\Payout;

class FakePayoutProvider implements PayoutProvider
{
    public int $sendCallCount = 0;

    public int $checkStatusCallCount = 0;

    private ProviderResultStatus $sendResult = ProviderResultStatus::Success;

    private ProviderResultStatus $checkStatusResult = ProviderResultStatus::Success;

    public function forceSendResult(ProviderResultStatus $status): self
    {
        $this->sendResult = $status;

        return $this;
    }

    public function forceCheckStatusResult(ProviderResultStatus $status): self
    {
        $this->checkStatusResult = $status;

        return $this;
    }

    public function resetCallCounts(): self
    {
        $this->sendCallCount = 0;
        $this->checkStatusCallCount = 0;

        return $this;
    }

    public function send(Payout $payout): PayoutProviderResult
    {
        $this->sendCallCount++;

        return new PayoutProviderResult(
            status: $this->sendResult,
            providerReference: 'fake-send-'.$payout->id,
            message: 'fake send',
        );
    }

    public function checkStatus(Payout $payout): PayoutProviderResult
    {
        $this->checkStatusCallCount++;

        return new PayoutProviderResult(
            status: $this->checkStatusResult,
            providerReference: 'fake-check-'.$payout->id,
            message: 'fake check',
        );
    }
}
