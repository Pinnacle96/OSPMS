<?php

namespace App\Domains\Ticketing\Queries;

use App\Domains\Identity\Models\User;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Services\TicketVerificationService;
use Spatie\Activitylog\Models\Activity;

class TicketDetailQuery
{
    public function get(User $user, Ticket $ticket): array
    {
        $verification = app(TicketVerificationService::class);
        $links = [];
        foreach (['lga' => 'lgas', 'park' => 'parks', 'operator' => 'operators', 'driver' => 'drivers', 'vehicle' => 'vehicles', 'route' => 'routes', 'revenueHead' => 'revenue-heads', 'feeConfiguration' => 'fee-configurations'] as $relation => $path) {
            $record = $ticket->{$relation};
            if ($record && $user->can('view', $record)) {
                $links[$relation] = '/'.$path.'/'.$record->public_id;
            }
        }

        return [
            'ticket' => [
                ...$verification->safe($ticket), 'public_id' => $ticket->public_id,
                'fee_code' => $ticket->fee_code_snapshot, 'fee_name_snapshot' => $ticket->fee_name_snapshot,
                'context' => $ticket->context_snapshot, 'issuer' => $ticket->issuer?->name,
            ],
            'links' => $links, 'verification_url' => $verification->url($ticket), 'qr_image' => $verification->qr($ticket),
            'can_cancel' => $user->can('cancel', $ticket),
            'activities' => $user->can('view_audit_log') ? Activity::forSubject($ticket)->latest('id')->limit(30)->get(['description', 'properties', 'created_at']) : [],
        ];
    }
}
