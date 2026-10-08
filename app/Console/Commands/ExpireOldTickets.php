<?php

namespace App\Console\Commands;

use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketExpiryService;
use Illuminate\Console\Command;

class ExpireOldTickets extends Command
{
    protected $signature = 'ospm:tickets-expire';

    protected $description = 'Expire tickets whose configured validity period has ended, retaining their terms and history';

    public function handle(TicketExpiryService $service): int
    {
        $count = 0;
        Ticket::whereIn('ticket_status', ['pending', 'paid'])->where('expires_at', '<=', now())->chunkById(100, function ($tickets) use ($service, &$count) {
            foreach ($tickets as $ticket) {
                $count += (int) $service->expire($ticket);
            }
        });
        $this->info($count.' tickets expired.');

        return self::SUCCESS;
    }
}
