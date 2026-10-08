<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Drivers\Models\Driver;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Routes\Models\Route;
use App\Domains\Ticketing\Queries\TicketListQuery;
use App\Domains\Vehicles\Models\Vehicle;

class TransportConnectionsQuery
{
    public function get(User $user, Lga|Park|Route $record): array
    {
        $s = app(UserAccessScopeService::class);
        $parks = $s->scopeParks(Park::query(), $user);
        if ($record instanceof Lga) {
            $parks->where('lga_id', $record->id);
        } elseif ($record instanceof Park) {
            $parks->whereKey($record->id);
        } else {
            $parks->whereHas('routes', fn ($r) => $r->where('routes.id', $record->id));
        }
        $ids = $parks->select('parks.id');
        $result = [];
        foreach (['operators' => Operator::class, 'drivers' => Driver::class, 'vehicles' => Vehicle::class] as $key => $model) {
            if (! $user->can('view_'.rtrim($key, 's'))) {
                continue;
            }
            $q = $model::query();
            if ($key === 'operators') {
                $s->scopeOperators($q, $user)->whereHas('parks', fn ($p) => $p->whereIn('parks.id', (clone $ids)));
                if ($record instanceof Route) {
                    $q->whereHas('routes', fn ($r) => $r->where('routes.id', $record->id)->whereIn('operator_route.park_id', (clone $ids)));
                }
                $columns = ['id', 'public_id', 'name', 'operator_number', 'status'];
            } else {
                $s->scopeParticipants($q, $user)->whereHas('assignments', function ($a) use ($s, $user, $ids, $record) {
                    $s->scopeAssignments($a, $user)->whereIn('park_id', (clone $ids));
                    if ($record instanceof Route) {
                        $a->where('route_id', $record->id);
                    }
                });
                $columns = $key === 'drivers' ? ['id', 'public_id', 'first_name', 'last_name', 'driver_number', 'status'] : ['id', 'public_id', 'registration_number', 'vehicle_number', 'status'];
            }
            $result[$key] = $q->orderBy('id')->paginate(10, $columns, $key.'_page')->withQueryString();
        }

        if ($record instanceof Park && $user->can('view_ticket')) {
            $result['tickets'] = app(TicketListQuery::class)->get($user, ['park_id' => $record->id], 'tickets_page');
        }

        return $result;
    }
}
