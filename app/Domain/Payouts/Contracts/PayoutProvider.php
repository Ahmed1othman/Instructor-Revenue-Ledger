<?php

namespace App\Domain\Payouts\Contracts;

use App\Domain\Payouts\DTOs\PayoutProviderResult;
use App\Models\Payout;

interface PayoutProvider
{
    public function send(Payout $payout): PayoutProviderResult;

    public function checkStatus(Payout $payout): PayoutProviderResult;
}
