<?php

namespace App\Domain\Ledger\Enums;

enum LedgerEntryType: string
{
    case EarningCredit = 'earning_credit';
    case PayoutDebit = 'payout_debit';
}
