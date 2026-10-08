<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Finance\Models\SettlementItem;
use Illuminate\Database\Eloquent\Builder;

class SettlementService
{
    public function eligible(array $period): Builder
    {
        return FinancialTransaction::where('direction', 'credit')->where('transaction_type', 'payment')->where('currency', 'NGN')->where('amount', '>', 0)
            ->where('occurred_at', '>=', $period['period_start'])->where('occurred_at', '<', $period['period_end'])
            ->whereHas('ticket', fn ($t) => $t->whereColumn('tickets.amount', 'financial_transactions.amount')->whereColumn('tickets.currency', 'financial_transactions.currency'))
            ->whereHas('payment', fn ($p) => $p->where('provider', 'demo')->where('status', 'successful')
                ->whereColumn('payments.ticket_id', 'financial_transactions.ticket_id')->whereColumn('payments.amount', 'financial_transactions.amount')->whereColumn('payments.currency', 'financial_transactions.currency'))
            ->whereNotIn('id', SettlementItem::select('financial_transaction_id'))
            ->whereDoesntHave('reversals')
            ->whereNotIn('payment_id', FinancialTransaction::whereIn('transaction_type', ['refund', 'adjustment'])->whereNotNull('payment_id')->select('payment_id'));
    }
}
