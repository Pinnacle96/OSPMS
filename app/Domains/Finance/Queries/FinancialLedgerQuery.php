<?php

namespace App\Domains\Finance\Queries;

use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use Carbon\CarbonImmutable;

class FinancialLedgerQuery
{
    public function get(User $user, array $filters = [])
    {
        $q = app(UserAccessScopeService::class)->scopeLedger(FinancialTransaction::query(), $user)->with(['ticket', 'payment']);
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $q->where(fn ($q) => $q->where('transaction_reference', 'like', $term)->orWhereHas('ticket', fn ($t) => $t->where('ticket_reference', 'like', $term)->orWhere('context_snapshot->vehicle->registration', 'like', $term))->orWhereHas('payment', fn ($p) => $p->where('payment_reference', 'like', $term)));
        }
        foreach (['direction', 'transaction_type'] as $field) {
            if (! empty($filters[$field])) {
                $q->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['channel'])) {
            $q->whereHas('payment', fn ($p) => $p->where('channel', $filters['channel']));
        }
        foreach (['lga_id', 'park_id', 'revenue_head_id', 'operator_id', 'driver_id', 'vehicle_id'] as $field) {
            if (! empty($filters[$field])) {
                $q->whereHas('ticket', fn ($t) => $t->where($field, $filters[$field]));
            }
        }
        if (! empty($filters['from'])) {
            $q->where('occurred_at', '>=', $filters['from'].' 00:00:00');
        } if (! empty($filters['to'])) {
            $q->where('occurred_at', '<', CarbonImmutable::parse($filters['to'])->addDay());
        }
        $sort = in_array($filters['sort'] ?? '', ['amount', 'occurred_at', 'transaction_reference'], true) ? $filters['sort'] : 'occurred_at';

        return $q->orderBy($sort, ($filters['order'] ?? '') === 'asc' ? 'asc' : 'desc')->orderByDesc('id')->paginate(15)->withQueryString()->through(fn ($t) => [
            'public_id' => $t->public_id, 'transaction_reference' => $t->transaction_reference, 'transaction_type' => $t->transaction_type, 'direction' => $t->direction, 'amount' => $t->amount, 'currency' => $t->currency, 'occurred_at' => $t->occurred_at, 'ticket_reference' => $t->ticket?->ticket_reference, 'payment_reference' => $t->payment?->payment_reference,
        ]);
    }
}
