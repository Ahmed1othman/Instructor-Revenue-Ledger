<?php

namespace App\Domain\Payouts\DTOs;

use App\Domain\Payouts\Enums\ProviderResultStatus;

final class PayoutProviderResult
{
    public function __construct(
        public readonly ProviderResultStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $message = null,
    ) {}
}
