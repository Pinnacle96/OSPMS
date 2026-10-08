<?php

namespace App\Domains\Payments\Events;

use App\Domains\Payments\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PaymentReversed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Payment $payment) {}
}
