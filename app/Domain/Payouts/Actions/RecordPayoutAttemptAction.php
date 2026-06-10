<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Payouts\DTOs\PayoutProviderResult;
use App\Domain\Payouts\Enums\PayoutAttemptStatus;
use App\Domain\Payouts\Enums\ProviderResultStatus;
use App\Models\Payout;
use App\Models\PayoutAttempt;

class RecordPayoutAttemptAction
{
    public function execute(Payout $payout, string $type, PayoutProviderResult $result): PayoutAttempt
    {
        return PayoutAttempt::query()->create([
            'payout_id' => $payout->id,
            'type' => $type,
            'status' => $this->attemptStatus($result->status),
            'provider_result' => $result->status,
            'provider_reference' => $result->providerReference,
            'idempotency_key' => $payout->provider_idempotency_key,
            'attempted_at' => now(),
            'response_payload' => [
                'message' => $result->message,
            ],
        ]);
    }

    private function attemptStatus(ProviderResultStatus $status): PayoutAttemptStatus
    {
        return match ($status) {
            ProviderResultStatus::Success => PayoutAttemptStatus::Succeeded,
            ProviderResultStatus::PermanentFailure => PayoutAttemptStatus::Failed,
            ProviderResultStatus::TimeoutUnknown => PayoutAttemptStatus::Timeout,
        };
    }
}
