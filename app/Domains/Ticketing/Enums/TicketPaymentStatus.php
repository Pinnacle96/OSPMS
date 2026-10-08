<?php

namespace App\Domains\Ticketing\Enums;

enum TicketPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Reversed = 'reversed';
    case Refunded = 'refunded';
}
