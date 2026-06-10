<?php

namespace App\Domain\Ledger\Enums;

enum LedgerDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
