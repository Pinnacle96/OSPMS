<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Finance\Actions\CreateLedgerCreditAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\RecordNotificationService;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Payments\Events\PaymentSucceeded;
use App\Domains\Payments\Models\Payment;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketExpiryService;
use App\Notifications\PaymentSuccessfulNotification;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordSuccessfulPaymentAction
{
    public function execute(User $actor, Payment $payment): Payment
    {
        return DB::transaction(function () use ($actor, $payment) {
            $ticket = Ticket::whereKey($payment->ticket_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('pay', $ticket);
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->status === PaymentStatus::Successful) {
                return $p;
            }
            if ($p->status !== PaymentStatus::Pending || app(TicketExpiryService::class)->status($ticket)->value !== 'pending' || ! in_array($ticket->payment_status->value, ['unpaid', 'pending', 'failed'], true)) {
                throw ValidationException::withMessages(['scenario' => 'This payment or ticket is no longer eligible for success.']);
            }
            $verified = app(PaymentGatewayManager::class)->gateway()->verify($p->payment_reference);
            if ($verified->status !== PaymentStatus::Successful || $verified->reference !== $p->payment_reference || $verified->providerReference !== $p->provider_reference || $verified->amount !== $p->amount || $verified->currency !== $p->currency || $p->amount !== $ticket->amount || $p->currency !== $ticket->currency) {
                throw ValidationException::withMessages(['scenario' => 'Verified payment terms do not match this ticket.']);
            }
            $p->update(['status' => PaymentStatus::Successful, 'paid_at' => now()]);
            $ticket->update(['ticket_status' => 'paid', 'payment_status' => 'paid']);
            app(CreateLedgerCreditAction::class)->execute($p);
            app(GenerateReceiptAction::class)->execute($p);
            app(FinancialAuditService::class)->record($actor, $p, 'payment_successful', ['ticket_reference' => $ticket->ticket_reference]);
            activity('payments')->causedBy($actor)->performedOn($p)->log('payment_successful');
            app(RecordNotificationService::class)->send(User::find($p->created_by), $p, 'payment', 'success', $p->payment_reference, 'Payment recorded successfully', PaymentSuccessfulNotification::class);
            PaymentSucceeded::dispatch($p);

            return $p;
        }, 3);
    }
}
