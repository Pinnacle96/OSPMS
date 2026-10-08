<?php

namespace App\Domains\Reconciliation\Services;

use Illuminate\Database\Eloquent\Builder;

class PendingReconciliation
{
    public function count(Builder $ledger): int
    {
        $accepted = app(LatestReconciliation::class)->query()->whereIn('status', ['matched', 'reconciled'])->whereNotNull('ticket_id')
            ->where(fn ($q) => $q->whereNotNull('payment_id')->orWhereNull('financial_transaction_id'))->select('ticket_id');

        return (clone $ledger)->where('direction', 'credit')->whereNotIn('ticket_id', $accepted)->count();
    }
}
