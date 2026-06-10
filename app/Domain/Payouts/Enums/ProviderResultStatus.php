<?php

namespace App\Domain\Payouts\Enums;

enum ProviderResultStatus: string
{
    case Success = 'success';
    case PermanentFailure = 'permanent_failure';
    case TimeoutUnknown = 'timeout_unknown';
}
