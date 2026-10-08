<?php

namespace App\Support\Payments\DTOs;

use App\Domains\Payments\Enums\PaymentStatus;

final readonly class PaymentVerificationResult
{
    public function __construct(public string $reference, public string $providerReference, public string $amount, public string $currency, public PaymentStatus $status) {}
}
