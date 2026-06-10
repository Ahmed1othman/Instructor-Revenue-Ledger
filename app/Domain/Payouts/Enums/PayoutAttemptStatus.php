<?php

namespace App\Domain\Payouts\Enums;

enum PayoutAttemptStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Timeout = 'timeout';
}
