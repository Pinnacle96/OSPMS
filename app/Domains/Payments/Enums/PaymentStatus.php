<?php

namespace App\Domains\Payments\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Reversed = 'reversed';
    case Refunded = 'refunded';
}
