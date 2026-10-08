<?php

namespace App\Domains\Ticketing\Policies;

use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Ticketing\Models\Ticket;
use App\Support\Payments\PaymentGatewayManager;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_ticket');
    }

    public function create(User $user): bool
    {
        return $user->can('issue_ticket');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->viewAny($user) && app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $user)->whereKey($ticket->id)->exists();
    }

    public function pay(User $user, Ticket $ticket): bool
    {
        return $user->can('collect_payment') && app(PaymentGatewayManager::class)->demoEnabled() && app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $user, AccessLevel::Manage)->whereKey($ticket->id)->exists();
    }

    public function cancel(User $user, Ticket $ticket): bool
    {
        return $user->can('cancel_ticket') && app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $user, AccessLevel::Manage)->whereKey($ticket->id)->exists();
    }
}
