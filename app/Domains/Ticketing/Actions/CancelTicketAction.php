<?php

namespace App\Domains\Ticketing\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Ticketing\Enums\TicketPaymentStatus;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Domains\Ticketing\Events\TicketCancelled;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketExpiryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CancelTicketAction
{
    public function execute(User $user, Ticket $ticket, string $reason): Ticket
    {
        return DB::transaction(function () use ($user, $ticket, $reason) {
            $fresh = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('cancel', $fresh);
            $reason = trim($reason);
            Validator::make(['reason' => $reason], ['reason' => 'required|string|max:2000'])->validate();
            if (app(TicketExpiryService::class)->status($fresh) !== TicketStatus::Pending || ! in_array($fresh->payment_status, [TicketPaymentStatus::Unpaid, TicketPaymentStatus::Failed], true)) {
                throw ValidationException::withMessages(['reason' => 'Only pending, unpaid tickets without a payment in progress may be cancelled.']);
            }
            $fresh->update(['ticket_status' => TicketStatus::Cancelled]);
            activity('ticketing')->causedBy($user)->performedOn($fresh)->withProperties(['reference' => $fresh->ticket_reference, 'reason' => $reason])->log('ticket_cancelled');
            TicketCancelled::dispatch($fresh);

            return $fresh;
        });
    }
}
