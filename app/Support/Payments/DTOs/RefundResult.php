<?php

namespace App\Support\Payments\DTOs;

use App\Domains\Finance\Enums\RefundStatus;

readonly class RefundResult
{
    public function __construct(public string $refundReference, public string $providerReference, public string $amount, public string $currency, public RefundStatus $status) {}
}
