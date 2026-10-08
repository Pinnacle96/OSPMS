<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Refund;
use App\Domains\Finance\Services\FinancialConfirmationService;
use App\Domains\Finance\Services\FinancialIntegrityService;
use App\Domains\Identity\Models\User;
use App\Domains\Payments\Models\Payment;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RequestRefundAction
{
    public function execute(User $actor, Payment $payment, array $input): Refund
    {
        Gate::forUser($actor)->authorize('request', [Refund::class, $payment]);
        app(PaymentGatewayManager::class)->gateway();
        $s = app(FinancialIntegrityService::class);
        $data = $s->input($input);

        return app(FinancialConfirmationService::class)->execute($actor, $data['idempotency_key'], 'refund.request', ['payment' => $payment->public_id, 'amount' => $data['amount'], 'reason' => $data['reason']], Refund::class, function () use ($actor, $payment, $s, $data) {
            $p = $s->lock($payment);
            Gate::forUser($actor)->authorize('request', [Refund::class, $p]);
            if ($p->status->value !== 'successful' || $p->provider !== 'demo') {
                $s->fail('Only successful demo payments can be refunded.');
            }
            $s->fits($data['amount'], $s->remaining($p)['refundable']);
            $r = Refund::create(['refund_reference' => 'OSPM-REF-'.Str::ulid(), 'payment_id' => $p->id, 'ticket_id' => $p->ticket_id, 'amount' => $data['amount'], 'currency' => $p->currency, 'reason' => $data['reason'], 'status' => 'requested', 'requested_by' => $actor->id, 'requested_at' => now()]);
            $s->audit($actor, $r, 'refund.requested', $data['reason'], ['payment_reference' => $p->payment_reference]);

            return $r;
        });
    }
}
