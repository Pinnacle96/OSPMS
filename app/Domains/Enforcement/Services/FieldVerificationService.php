<?php

namespace App\Domains\Enforcement\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Services\ReceiptVerificationService;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketVerificationService;
use Illuminate\Support\Facades\Gate;

class FieldVerificationService
{
    public function ticket(User $u, string $token, string $kind): ?Ticket
    {
        Gate::forUser($u)->authorize('access_field');
        Gate::forUser($u)->authorize('verify_ticket');
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $token) || ! in_array($kind, ['ticket', 'receipt'], true)) {
            return null;
        }
        $q = app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $u);

        return $kind === 'ticket' ? $q->where('verification_token', $token)->first() : $q->whereIn('id', Receipt::where('verification_token', $token)->select('ticket_id'))->first();
    }

    public function verify(User $u, string $token, string $kind): ?array
    {
        $t = $this->ticket($u, $token, $kind);
        if (! $t) {
            return null;
        }
        $ticket = app(TicketVerificationService::class)->safe($t);
        $receipt = $kind === 'receipt' ? app(ReceiptVerificationService::class)->safe(Receipt::where('verification_token', $token)->firstOrFail()) : null;

        return ['kind' => $kind, 'ticket' => $ticket, 'receipt' => $receipt, 'checked_at' => now()->toIso8601String(), 'inspection_url' => $u->can('record_inspection') ? '/field/inspections/create?ticket='.$t->public_id : null];
    }
}
