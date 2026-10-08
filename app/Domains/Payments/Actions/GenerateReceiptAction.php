<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateReceiptAction
{
    public function execute(Payment $payment): Receipt
    {
        if (! DB::transactionLevel() || $payment->status !== PaymentStatus::Successful) {
            throw new \LogicException('Receipt requires a successful payment transaction.');
        }

        return Receipt::firstOrCreate(['payment_id' => $payment->id], ['ticket_id' => $payment->ticket_id, 'receipt_number' => 'RCPT-'.now()->year.'-'.Str::ulid(), 'verification_token' => bin2hex(random_bytes(32)), 'issued_at' => now(), 'created_at' => now()]);
    }
}
