<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\Refund;
use App\Domains\Finance\Services\FinancialConfirmationService;
use App\Domains\Finance\Services\FinancialIntegrityService;
use App\Domains\Identity\Models\User;
use App\Support\Payments\PaymentGatewayManager;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;

class ProcessRefundAction
{
    public function execute(User $actor, Refund $refund, string $scenario, string $reason, string $key): Refund
    {
        Gate::forUser($actor)->authorize('process', $refund);
        $gateway = app(PaymentGatewayManager::class)->gateway();
        $s = app(FinancialIntegrityService::class);
        $reason = $s->reason($reason);

        return app(FinancialConfirmationService::class)->execute($actor, $key, 'refund.process', ['refund' => $refund->public_id, 'scenario' => $scenario, 'reason' => $reason], Refund::class, function () use ($actor, $refund, $scenario, $reason, $s, $gateway) {
            $p = $s->lock($refund->payment);
            $r = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('process', $r);
            if (! in_array($r->status->value, ['approved', 'processing', 'failed']) || ! $r->approved_by || $p->status->value !== 'successful' || $p->provider !== 'demo') {
                $s->fail('Refund needs independent approval and a successful demo payment.');
            }
            $s->fits($r->amount, $s->remaining($p, $r->id)['refundable']);
            $previous = $r->status->value;
            $result = $gateway->refund($p->payment_reference, $r->refund_reference, $r->amount, $r->currency, $scenario);
            if ($result->refundReference !== $r->refund_reference || $result->providerReference !== 'DEMO-'.$r->refund_reference || $result->currency !== $r->currency || ! BigDecimal::of($result->amount)->isEqualTo($r->amount) || ! in_array($result->status->value, ['successful', 'failed', 'processing'])) {
                $s->fail('Provider refund response does not match this request.');
            }
            if ($result->status->value === 'successful') {
                $parent = FinancialTransaction::where('payment_id', $p->id)->where('transaction_type', 'payment')->where('direction', 'credit')->firstOrFail();
                app(CreateLedgerDebitAction::class)->execute($actor, $parent, 'REF-'.$r->refund_reference, $r->amount, 'refund', $r->reason);
            }
            $r->update(['status' => $result->status, 'provider_reference' => $result->providerReference, 'processed_at' => $result->status->value === 'processing' ? null : now()]);
            if ($result->status->value === 'successful' && BigDecimal::of($s->remaining($p)['refunded'])->isEqualTo($p->amount)) {
                $p->update(['status' => 'refunded']);
                $p->ticket->update(['payment_status' => 'refunded']);
            }
            $s->audit($actor, $r, 'refund.'.$result->status->value, $reason, ['previous_status' => $previous, 'provider_reference' => $result->providerReference, 'payment_reference' => $p->payment_reference]);

            return $r;
        });
    }
}
