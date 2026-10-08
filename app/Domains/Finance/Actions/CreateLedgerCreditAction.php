<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

class CreateLedgerCreditAction
{
    public function execute(Payment $payment): FinancialTransaction
    {
        if (! DB::transactionLevel() || $payment->status !== PaymentStatus::Successful) {
            throw new \LogicException('Ledger credit requires a successful payment transaction.');
        }

        return FinancialTransaction::firstOrCreate(['transaction_reference' => 'TXN-'.$payment->payment_reference], [
            'ticket_id' => $payment->ticket_id, 'payment_id' => $payment->id, 'transaction_type' => 'payment', 'direction' => 'credit', 'amount' => $payment->amount, 'currency' => $payment->currency, 'occurred_at' => $payment->paid_at, 'description' => 'Demo ticket payment', 'source' => 'payment_gateway', 'created_by' => $payment->created_by, 'metadata' => ['demo' => true], 'created_at' => now(),
        ]);
    }
}
