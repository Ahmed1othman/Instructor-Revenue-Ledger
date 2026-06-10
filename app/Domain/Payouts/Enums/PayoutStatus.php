<?php

namespace App\Domain\Payouts\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case PendingConfirmation = 'pending_confirmation';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
