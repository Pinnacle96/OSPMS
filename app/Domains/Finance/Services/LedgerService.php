<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Identity\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function appendCorrection(User $actor, FinancialTransaction $parent, string $reference, string $amount, string $type, string $reason, string $direction): FinancialTransaction
    {
        if (! DB::transactionLevel() || ! in_array($direction, ['debit', 'credit'], true) || ! in_array($type, ['refund', 'adjustment'], true) || ($type === 'refund' && $direction !== 'debit') || BigDecimal::of($amount)->isLessThanOrEqualTo(0)) {
            throw new \LogicException('Ledger corrections require a transaction, positive amount and supported direction.');
        }

        return FinancialTransaction::create(['transaction_reference' => $reference, 'ticket_id' => $parent->ticket_id, 'payment_id' => $parent->payment_id, 'parent_transaction_id' => $parent->id, 'transaction_type' => $type, 'direction' => $direction, 'amount' => $amount, 'currency' => $parent->currency, 'occurred_at' => now(), 'description' => ucfirst($type).' approved financial correction', 'source' => 'administrator', 'created_by' => $actor->id, 'metadata' => ['reason' => $reason, 'demo' => $parent->payment?->provider === 'demo'], 'created_at' => now()]);
    }
}
