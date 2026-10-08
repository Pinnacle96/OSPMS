<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Services\PaymentIdempotencyService;
use App\Domains\Ticketing\Models\Ticket;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResolveDemoPaymentAction
{
    public function execute(User $actor, Payment $payment, string $scenario, string $key): Payment
    {
        Gate::forUser($actor)->authorize('pay', $payment->ticket);
        app(PaymentGatewayManager::class)->gateway();

        return app(PaymentIdempotencyService::class)->execute($actor, $key, 'resolve_demo_payment', ['payment' => $payment->public_id, 'scenario' => $scenario], function () use ($actor, $payment, $scenario) {
            Ticket::whereKey($payment->ticket_id)->lockForUpdate()->firstOrFail();
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->provider !== 'demo' || $p->status->value !== 'pending' || ! in_array($scenario, ['successful', 'failed'], true)) {
                throw ValidationException::withMessages(['scenario' => 'Only a pending demo attempt can be resolved.']);
            }
            $p->update(['provider_metadata' => ['demo' => true, 'scenario' => $scenario]]);

            return $scenario === 'successful' ? app(RecordSuccessfulPaymentAction::class)->execute($actor, $p) : app(RecordFailedPaymentAction::class)->execute($actor, $p);
        });
    }
}
