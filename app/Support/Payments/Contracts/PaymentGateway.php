<?php

namespace App\Support\Payments\Contracts;

use App\Support\Payments\DTOs\PaymentInitiationResult;
use App\Support\Payments\DTOs\PaymentVerificationResult;
use App\Support\Payments\DTOs\RefundResult;

interface PaymentGateway
{
    public function initiate(string $reference, string $amount, string $currency, string $scenario): PaymentInitiationResult;

    public function refund(string $paymentReference, string $refundReference, string $amount, string $currency, string $scenario): RefundResult;

    public function verify(string $reference): PaymentVerificationResult;
}
