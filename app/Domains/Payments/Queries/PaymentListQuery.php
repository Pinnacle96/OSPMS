<?php

namespace App\Domains\Payments\Queries;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Payments\Models\Payment;

class PaymentListQuery
{
    public function get(User $user, array $filters = [], ?int $ticketId = null)
    {
        $q = app(UserAccessScopeService::class)->scopePayments(Payment::query(), $user)->with('ticket');
        if ($ticketId) {
            $q->where('ticket_id', $ticketId);
        }
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $q->where(fn ($q) => $q->where('payment_reference', 'like', $term)->orWhere('provider_reference', 'like', $term)->orWhereHas('ticket', fn ($t) => $t->where('ticket_reference', 'like', $term)->orWhere('context_snapshot->vehicle->registration', 'like', $term)));
        }
        foreach (['status', 'channel'] as $field) {
            if (! empty($filters[$field])) {
                $q->where($field, $filters[$field]);
            }
        }
        foreach (['lga_id', 'park_id', 'revenue_head_id', 'operator_id', 'driver_id', 'vehicle_id'] as $field) {
            if (! empty($filters[$field])) {
                $q->whereHas('ticket', fn ($t) => $t->where($field, $filters[$field]));
            }
        }
        app(FinancialDateFilter::class)->apply($q, 'initiated_at', $filters);
        $sort = in_array($filters['sort'] ?? '', ['amount', 'initiated_at', 'payment_reference'], true) ? $filters['sort'] : 'initiated_at';

        return $q->orderBy($sort, ($filters['order'] ?? '') === 'asc' ? 'asc' : 'desc')->orderByDesc('id')->paginate(15)->withQueryString()->through(fn ($p) => [
            'public_id' => $p->public_id, 'payment_reference' => $p->payment_reference, 'ticket_reference' => $p->ticket->ticket_reference, 'ticket_public_id' => $p->ticket->public_id, 'vehicle' => $p->ticket->context_snapshot['vehicle']['registration'] ?? null, 'park' => $p->ticket->context_snapshot['park']['name'] ?? null, 'amount' => $p->amount, 'currency' => $p->currency, 'provider' => $p->provider, 'channel' => $p->channel, 'status' => $p->status->value, 'initiated_at' => $p->initiated_at, 'paid_at' => $p->paid_at,
        ]);
    }
}
