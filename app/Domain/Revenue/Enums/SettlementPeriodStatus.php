<?php

namespace App\Domain\Revenue\Enums;

enum SettlementPeriodStatus: string
{
    case Open = 'open';
    case Allocating = 'allocating';
    case Allocated = 'allocated';
    case Closed = 'closed';
}
