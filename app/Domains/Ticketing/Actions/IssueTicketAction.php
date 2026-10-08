<?php

namespace App\Domains\Ticketing\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Ticketing\DTOs\IssueTicketData;
use App\Domains\Ticketing\Enums\TicketPaymentStatus;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Domains\Ticketing\Events\TicketIssued;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use App\Domains\Ticketing\Services\TicketReferenceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueTicketAction
{
    public function execute(User $user, IssueTicketData $data): Ticket
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $data) {
                    $review = app(TicketIssuanceService::class)->review($user, $data->assignmentId, $data->revenueHeadId, true, $data->requestKey);
                    if (! hash_equals($review['confirmation'], $data->confirmation)) {
                        throw ValidationException::withMessages(['confirmation' => 'The fee or operating context changed. Review the details again before issuing.']);
                    }
                    // The driver lock serializes repeated submissions of this reviewed request.
                    $existing = Ticket::where('driver_id', $review['attributes']['driver_id'])->where('issued_by', $user->id)
                        ->where('context_snapshot->issuance_request_key', $data->requestKey)->lockForUpdate()->first();
                    if ($existing) {
                        return $existing;
                    }
                    $ticket = Ticket::create($review['attributes'] + [
                        'ticket_reference' => app(TicketReferenceService::class)->generate(),
                        'verification_token' => bin2hex(random_bytes(32)), 'issued_by' => $user->id, 'issued_at' => now(),
                        'ticket_status' => TicketStatus::Pending, 'payment_status' => TicketPaymentStatus::Unpaid,
                    ]);
                    activity('ticketing')->causedBy($user)->performedOn($ticket)->withProperties(['reference' => $ticket->ticket_reference, 'amount' => $ticket->amount, 'currency' => $ticket->currency, 'fee_configuration_id' => $ticket->fee_configuration_id])->log('ticket_created');
                    TicketIssued::dispatch($ticket);

                    return $ticket;
                }, 3);
            } catch (UniqueConstraintViolationException $error) {
                if ($attempt === 2 || ! str_contains($error->getSql(), 'tickets')) {
                    throw $error;
                }
            }
        }
        throw new \LogicException('Ticket reference retry exhausted.');
    }
}
