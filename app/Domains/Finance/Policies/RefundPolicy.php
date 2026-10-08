<?php

namespace App\Domains\Finance\Policies;

use App\Domains\Finance\Models\Refund;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Payments\Models\Payment;
use App\Domains\Ticketing\Models\Ticket;

class RefundPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_refund');
    }

    public function view(User $u, Refund $r): bool
    {
        return $this->viewAny($u) && app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $u)->whereKey($r->ticket_id)->exists();
    }

    public function request(User $u, Payment $p): bool
    {
        return $u->can('request_refund') && app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $u, AccessLevel::Manage)->whereKey($p->ticket_id)->exists();
    }

    public function approve(User $u, Refund $r): bool
    {
        return $this->view($u, $r) && $u->can('approve_refund') && $u->can('access_statewide') && $u->id !== $r->requested_by;
    }

    public function process(User $u, Refund $r): bool
    {
        return $this->view($u, $r) && $u->can('process_refund') && $u->can('access_statewide');
    }
}
