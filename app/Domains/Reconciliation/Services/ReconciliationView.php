<?php

namespace App\Domains\Reconciliation\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Reconciliation\Models\ReconciliationItem;
use App\Domains\Reconciliation\Models\ReconciliationRun;

class ReconciliationView
{
    public function run(ReconciliationRun $run, User $user): array
    {
        $summary = app(ReconciliationSummaryService::class)->get(app(ReconciliationAccess::class)->items($run->items()->getQuery(), $user));
        $status = $run->status->value;
        if (! $user->can('access_statewide') && str_starts_with($status, 'completed')) {
            $status = $summary['exceptions'] || $summary['under_review'] ? 'completed_with_exceptions' : 'completed';
        }

        return [...$run->only(['public_id', 'reconciliation_reference', 'period_start', 'period_end', 'provider', 'started_at', 'completed_at']), 'status' => $status, 'summary' => $summary, 'scoped' => ! $user->can('access_statewide')];
    }

    public function item(ReconciliationItem $item, User $user): array
    {
        return [...$item->only(['id', 'expected_amount', 'actual_amount', 'difference_amount', 'status', 'exception_type', 'resolution_note', 'resolved_at']), 'ticket_reference' => $item->ticket?->ticket_reference, 'ticket_url' => $item->ticket && $user->can('view', $item->ticket) ? '/tickets/'.$item->ticket->public_id : null, 'payment_reference' => $item->payment?->payment_reference, 'payment_url' => $item->payment && $user->can('view', $item->payment) ? '/payments/'.$item->payment->public_id : null, 'ledger_url' => $item->transaction && $user->can('view', $item->transaction) ? '/finance/ledger/'.$item->transaction->public_id : null, 'settlement_url' => $item->settlementItem && $user->can('view', $item->settlementItem->settlement) ? '/finance/settlements/'.$item->settlementItem->settlement->public_id : null, 'exception_url' => '/finance/reconciliation/items/'.$item->id];
    }
}
