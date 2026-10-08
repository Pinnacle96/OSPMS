<?php

namespace App\Support\Payments\Contracts;

use App\Support\Payments\DTOs\PaymentInitiationResult;
use App\Support\Payments\DTOs\PaymentVerificationResult;

interface PaymentGateway
{
    public function initiate(string $reference, string $amount, string $currency, string $scenario): PaymentInitiationResult;

    public function verify(string $reference): PaymentVerificationResult;
}
