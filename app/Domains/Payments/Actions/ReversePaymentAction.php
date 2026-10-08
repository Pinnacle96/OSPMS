<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Audit\Services\FinancialAuditService;
use App\Domains\Finance\Models\FinancialAdjustment;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\Refund;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Events\PaymentReversed;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Services\PaymentIdempotencyService;
use App\Domains\Ticketing\Models\Ticket;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReversePaymentAction
{
    public function execute(User $actor, Payment $payment, string $reason, string $key): Payment
    {
        Gate::forUser($actor)->authorize('reverse', $payment);
        app(PaymentGatewayManager::class)->gateway();
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|max:2000'])->validate();

        return app(PaymentIdempotencyService::class)->execute($actor, $key, 'reverse_demo_payment', ['payment' => $payment->public_id, 'reason' => $reason], function () use ($actor, $payment, $reason) {
            $ticket = Ticket::whereKey($payment->ticket_id)->lockForUpdate()->firstOrFail();
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('reverse', $p);
            if ($p->provider !== 'demo' || $p->status->value !== 'successful' || $ticket->payment_status->value !== 'paid') {
                throw ValidationException::withMessages(['reason' => 'Only a successful demo payment can be reversed.']);
            }
            $credit = FinancialTransaction::where('payment_id', $p->id)->where('transaction_type', 'payment')->where('direction', 'credit')->lockForUpdate()->firstOrFail();
            $refunds = Refund::where('payment_id', $p->id)->whereIn('status', ['requested', 'approved', 'processing', 'successful'])->lockForUpdate()->get();
            $adjustments = FinancialAdjustment::whereIn('original_transaction_id', FinancialTransaction::where('payment_id', $p->id)->select('id'))->whereIn('status', ['requested', 'approved'])->lockForUpdate()->get();
            if ($refunds->isNotEmpty() || $adjustments->isNotEmpty() || FinancialTransaction::where('payment_id', $p->id)->where('transaction_type', '!=', 'payment')->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['reason' => 'This payment has financial corrections or pending requests. Use the refund or adjustment workflow.']);
            }
            FinancialTransaction::create(['transaction_reference' => 'REV-'.$p->payment_reference, 'ticket_id' => $ticket->id, 'payment_id' => $p->id, 'parent_transaction_id' => $credit->id, 'transaction_type' => 'reversal', 'direction' => 'debit', 'amount' => $p->amount, 'currency' => $p->currency, 'occurred_at' => now(), 'description' => 'Controlled demo payment reversal', 'source' => 'administrator', 'created_by' => $actor->id, 'metadata' => ['demo' => true, 'reason' => $reason], 'created_at' => now()]);
            $p->update(['status' => 'reversed', 'reversed_at' => now()]);
            $ticket->update(['ticket_status' => 'reversed', 'payment_status' => 'reversed']);
            app(FinancialAuditService::class)->record($actor, $p, 'payment_reversed', ['reason' => $reason, 'original_transaction' => $credit->transaction_reference]);
            activity('payments')->causedBy($actor)->performedOn($p)->withProperties(['reason' => $reason])->log('payment_reversed');
            PaymentReversed::dispatch($p);

            return $p;
        });
    }
}
