<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Payments\Events\PaymentFailed;
use App\Domains\Payments\Models\Payment;
use App\Domains\Ticketing\Models\Ticket;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordFailedPaymentAction
{
    public function execute(User $actor, Payment $payment): Payment
    {
        return DB::transaction(function () use ($actor, $payment) {
            $ticket = Ticket::whereKey($payment->ticket_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('pay', $ticket);
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->status === PaymentStatus::Failed) {
                return $p;
            }
            $verified = app(PaymentGatewayManager::class)->gateway()->verify($p->payment_reference);
            if ($p->status !== PaymentStatus::Pending || $verified->status !== PaymentStatus::Failed) {
                throw ValidationException::withMessages(['scenario' => 'This payment cannot be marked failed.']);
            }
            $p->update(['status' => PaymentStatus::Failed, 'failed_at' => now()]);
            if ($ticket->payment_status->value === 'pending') {
                $ticket->update(['payment_status' => 'failed']);
            }
            app(FinancialAuditService::class)->record($actor, $p, 'payment_failed');
            activity('payments')->causedBy($actor)->performedOn($p)->log('payment_failed');
            PaymentFailed::dispatch($p);

            return $p;
        }, 3);
    }
}
