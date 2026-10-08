<?php

namespace App\Domains\Ticketing\Queries;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketExpiryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class TicketListQuery
{
    public function query(User $user, array $filters = []): Builder
    {
        $query = app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $user);
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where('ticket_reference', 'like', $term)->orWhere('fee_code_snapshot', 'like', $term)->orWhere('fee_name_snapshot', 'like', $term);
                foreach (['vehicle->registration', 'driver->name', 'driver->reference', 'operator->name', 'operator->reference', 'park->name'] as $field) {
                    $q->orWhere('context_snapshot->'.$field, 'like', $term);
                }
            });
        }
        foreach (['lga_id', 'park_id', 'revenue_head_id', 'operator_id', 'driver_id', 'vehicle_id', 'payment_status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['from'])) {
            $query->where('issued_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('issued_at', '<', CarbonImmutable::parse($filters['to'])->addDay());
        }
        if (! empty($filters['ticket_status'])) {
            $status = $filters['ticket_status'];
            if ($status === 'expired') {
                $query->where(fn ($q) => $q->where('ticket_status', 'expired')->orWhere(fn ($q) => $q->whereIn('ticket_status', ['pending', 'paid'])->where('expires_at', '<=', now())));
            } else {
                $query->where('ticket_status', $status);
                if (in_array($status, ['pending', 'paid'], true)) {
                    $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                }
            }
        }

        return $query;
    }

    public function get(User $user, array $filters = [], string $pageName = 'page')
    {
        $column = in_array($filters['sort'] ?? '', ['ticket_reference', 'issued_at', 'amount'], true) ? $filters['sort'] : 'issued_at';

        return $this->query($user, $filters)->orderBy($column, ($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc')->orderBy('id', 'desc')
            ->paginate(15, ['public_id', 'ticket_reference', 'fee_name_snapshot', 'amount', 'currency', 'ticket_status', 'payment_status', 'issued_at', 'expires_at', 'context_snapshot'], $pageName)->withQueryString()
            ->through(function ($ticket) {
                $data = $ticket->only(['public_id', 'ticket_reference', 'fee_name_snapshot', 'amount', 'currency', 'payment_status', 'issued_at', 'expires_at']);
                $data['ticket_status'] = app(TicketExpiryService::class)->status($ticket)->value;
                $data['vehicle'] = $ticket->context_snapshot['vehicle']['registration'] ?? null;
                $data['park'] = $ticket->context_snapshot['park']['name'] ?? null;

                return $data;
            });
    }
}
