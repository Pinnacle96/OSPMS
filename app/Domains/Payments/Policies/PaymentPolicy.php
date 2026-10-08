<?php

namespace App\Domains\Payments\Policies;

use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Payments\Models\Payment;
use App\Domains\Ticketing\Models\Ticket;
use App\Support\Payments\PaymentGatewayManager;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_payment');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user) && app(UserAccessScopeService::class)->scopePayments(Payment::query(), $user)->whereKey($payment->id)->exists();
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $user->can('reverse_transaction') && app(PaymentGatewayManager::class)->demoEnabled() && app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $user, AccessLevel::Manage)->whereKey($payment->ticket_id)->exists();
    }
}
