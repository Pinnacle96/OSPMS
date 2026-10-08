<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Services\PaymentIdempotencyService;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketExpiryService;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InitiatePaymentAction
{
    public function execute(User $actor, Ticket $ticket, string $scenario, string $key): Payment
    {
        Gate::forUser($actor)->authorize('pay', $ticket);
        app(PaymentGatewayManager::class)->gateway();

        return app(PaymentIdempotencyService::class)->execute($actor, $key, 'initiate_payment', ['ticket' => $ticket->public_id, 'scenario' => $scenario], function () use ($actor, $ticket, $scenario, $key) {
            $t = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('pay', $t);
            if (app(TicketExpiryService::class)->status($t)->value !== 'pending' || ! in_array($t->payment_status->value, ['unpaid', 'failed'], true)) {
                throw ValidationException::withMessages(['scenario' => 'Only an unexpired unpaid ticket without a payment in progress can be paid.']);
            }
            if ($t->amount === '0.00') {
                throw ValidationException::withMessages(['scenario' => 'This ticket has no positive amount due.']);
            }
            if (Payment::where('ticket_id', $t->id)->whereIn('status', ['pending', 'successful'])->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['scenario' => 'A pending or successful payment already exists for this ticket.']);
            }
            $reference = 'PAY-'.now()->year.'-'.Str::ulid();
            $result = app(PaymentGatewayManager::class)->gateway()->initiate($reference, $t->amount, $t->currency, $scenario);
            $p = Payment::create(['payment_reference' => $reference, 'ticket_id' => $t->id, 'provider' => 'demo', 'provider_reference' => $result->providerReference, 'channel' => 'demo', 'amount' => $t->amount, 'currency' => $t->currency, 'status' => 'pending', 'idempotency_key' => $key, 'initiated_at' => now(), 'provider_metadata' => $result->metadata, 'created_by' => $actor->id]);
            $t->update(['payment_status' => 'pending']);
            app(FinancialAuditService::class)->record($actor, $p, 'payment_initiated', ['scenario' => $scenario]);
            activity('payments')->causedBy($actor)->performedOn($p)->log('payment_initiated');

            return match ($result->status->value) {
                'successful' => app(RecordSuccessfulPaymentAction::class)->execute($actor, $p),'failed' => app(RecordFailedPaymentAction::class)->execute($actor, $p),default => $p
            };
        });
    }
}
