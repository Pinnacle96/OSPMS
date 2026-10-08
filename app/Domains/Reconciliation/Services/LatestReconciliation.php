<?php

namespace App\Domains\Reconciliation\Services;

use App\Domains\Reconciliation\Models\ReconciliationItem;
use Illuminate\Database\Eloquent\Builder;

class LatestReconciliation
{
    public function query(): Builder
    {
        return ReconciliationItem::query()->whereNotExists(function ($q) {
            $q->selectRaw('1')->from('reconciliation_items as newer')->whereColumn('newer.id', '>', 'reconciliation_items.id')->where(function ($same) {
                $same->where(function ($obligation) {
                    $obligation->whereColumn('newer.ticket_id', 'reconciliation_items.ticket_id')
                        ->where(fn ($n) => $n->whereNotNull('newer.payment_id')->orWhereNull('newer.financial_transaction_id'))
                        ->where(fn ($i) => $i->whereNotNull('reconciliation_items.payment_id')->orWhereNull('reconciliation_items.financial_transaction_id'));
                })->orWhere(function ($ledger) {
                    $ledger->whereNull('newer.payment_id')->whereNull('reconciliation_items.payment_id')->whereColumn('newer.financial_transaction_id', 'reconciliation_items.financial_transaction_id');
                })->orWhere(function ($payment) {
                    $payment->whereNull('newer.ticket_id')->whereNull('reconciliation_items.ticket_id')->whereColumn('newer.payment_id', 'reconciliation_items.payment_id');
                });
            });
        });
    }
}
