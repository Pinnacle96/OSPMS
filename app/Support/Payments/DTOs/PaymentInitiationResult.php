<?php

namespace App\Support\Payments\DTOs;

use App\Domains\Payments\Enums\PaymentStatus;

final readonly class PaymentInitiationResult
{
    public function __construct(public string $providerReference, public PaymentStatus $status, public array $metadata) {}
}
