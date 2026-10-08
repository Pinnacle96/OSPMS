<?php

namespace App\Domains\Finance\Enums;

enum SettlementStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Settled = 'settled';
    case Exception = 'exception';
    case Reversed = 'reversed';
}
