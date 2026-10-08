<?php

namespace App\Domains\Ticketing\Enums;

enum TicketStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
}
