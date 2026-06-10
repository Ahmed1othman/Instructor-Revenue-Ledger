<?php

namespace App\Domain\Revenue\Enums;

enum SettlementGranularity: string
{
    case Monthly = 'monthly';
    case Daily = 'daily';
}
