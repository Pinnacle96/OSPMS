<?php

namespace App\Domains\Ticketing\Services;

use App\Domains\Ticketing\Enums\TicketStatus;
use App\Domains\Ticketing\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketExpiryService
{
    public function status(Ticket $ticket): TicketStatus
    {
        if (in_array($ticket->ticket_status, [TicketStatus::Pending, TicketStatus::Paid], true) && $ticket->expires_at?->lte(now())) {
            return TicketStatus::Expired;
        }

        return $ticket->ticket_status;
    }

    public function expire(Ticket $ticket): bool
    {
        return DB::transaction(function () use ($ticket) {
            $fresh = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($fresh->ticket_status === TicketStatus::Expired || $this->status($fresh) !== TicketStatus::Expired) {
                return false;
            }
            $fresh->update(['ticket_status' => TicketStatus::Expired]);
            activity('ticketing')->performedOn($fresh)->withProperties(['reference' => $fresh->ticket_reference])->log('ticket_expired');

            return true;
        });
    }
}
