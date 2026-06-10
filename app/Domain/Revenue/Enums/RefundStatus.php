<?php

namespace App\Domain\Revenue\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
