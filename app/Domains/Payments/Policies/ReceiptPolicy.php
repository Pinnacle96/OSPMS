<?php

namespace App\Domains\Payments\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Ticketing\Models\Ticket;

class ReceiptPolicy
{
    public function view(User $user, Receipt $receipt): bool
    {
        return $user->can('view_receipt') && app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $user)->whereKey($receipt->ticket_id)->exists();
    }
}
