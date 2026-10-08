<?php

namespace App\Domains\Payments\Enums;

enum PaymentChannel: string
{
    case CashlessPos = 'cashless_pos';
    case Transfer = 'transfer';
    case Ussd = 'ussd';
    case Gateway = 'gateway';
    case Demo = 'demo';
    case Other = 'other';
}
