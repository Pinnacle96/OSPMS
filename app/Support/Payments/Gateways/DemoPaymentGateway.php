<?php

namespace App\Support\Payments\Gateways;

use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Payments\Models\Payment;
use App\Support\Payments\Contracts\PaymentGateway;
use App\Support\Payments\DTOs\PaymentInitiationResult;
use App\Support\Payments\DTOs\PaymentVerificationResult;
use Illuminate\Validation\ValidationException;

class DemoPaymentGateway implements PaymentGateway
{
    public function initiate(string $reference, string $amount, string $currency, string $scenario): PaymentInitiationResult
    {
        $status = PaymentStatus::tryFrom($scenario);
        if (! in_array($status, [PaymentStatus::Successful, PaymentStatus::Failed, PaymentStatus::Pending], true)) {
            throw ValidationException::withMessages(['scenario' => 'Select a supported demo outcome.']);
        }

        return new PaymentInitiationResult('DEMO-'.$reference, $status, ['demo' => true, 'scenario' => $scenario]);
    }

    public function verify(string $reference): PaymentVerificationResult
    {
        $payment = Payment::where('payment_reference', $reference)->where('provider', 'demo')->lockForUpdate()->firstOrFail();

        return new PaymentVerificationResult($reference, $payment->provider_reference, $payment->amount, $payment->currency, PaymentStatus::from($payment->provider_metadata['scenario']));
    }
}
