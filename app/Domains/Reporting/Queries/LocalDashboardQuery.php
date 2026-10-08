<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Drivers\Models\Driver;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Reporting\Services\DashboardDateRange;
use App\Domains\Routes\Models\Route;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Support\Facades\Gate;

class LocalDashboardQuery
{
    public function get(User $user, Lga|Park $record, array $filters = []): array
    {
        Gate::forUser($user)->authorize('view', $record);
        $lga = $record instanceof Lga;
        $filters[$lga ? 'lga_id' : 'park_id'] = $record->id;
        $range = app(DashboardDateRange::class)->resolve($filters);
        $finance = app(RevenueDashboardQuery::class)->get($user, $filters, $range, $record);
        $parks = app(UserAccessScopeService::class)->scopeParks(Park::query(), $user);
        if ($lga) {
            $parks->where('lga_id', $record->id);
        } else {
            $parks->whereKey($record->id);
        }
        $routes = Route::query()->where('status', 'active')->whereHas('parks', fn ($q) => $q->whereIn('parks.id', (clone $parks)->select('parks.id'))->where('park_route.status', 'active'))->count();
        $metrics = $lga ? [['label' => 'Total parks', 'value' => $parks->count(), 'note' => 'Within this LGA'], ['label' => 'Active parks', 'value' => (clone $parks)->where('status', 'active')->count(), 'note' => 'Operational status is active']] : [];
        $metrics[] = ['label' => 'Approved routes', 'value' => $routes, 'note' => 'Active routes and assignments'];
        $ids = (clone $parks)->select('parks.id');
        $scope = app(UserAccessScopeService::class);
        $operators = $scope->scopeOperators(Operator::query(), $user)->whereHas('parks', fn ($p) => $p->whereIn('parks.id', (clone $ids))->where('operator_park.status', 'active'))->count();
        $metrics[] = ['label' => 'Registered operators', 'value' => $operators, 'note' => 'Within this scope'];
        foreach (['vehicles' => Vehicle::class, 'drivers' => Driver::class] as $label => $model) {
            $count = $scope->scopeParticipants($model::query(), $user)->whereHas('assignments', fn ($a) => $scope->scopeAssignments($a, $user)->whereIn('park_id', (clone $ids)))->count();
            $metrics[] = ['label' => 'Registered '.$label, 'value' => $count, 'note' => 'Current and historical registrations'];
        }
        $tickets = app(UserAccessScopeService::class)->scopeTickets(Ticket::query(), $user)
            ->where($lga ? 'lga_id' : 'park_id', $record->id);
        $metrics[] = ['label' => 'Tickets', 'value' => $user->can('view_ticket') ? $tickets->count() : 0, 'note' => $user->can('view_ticket') ? 'Issued tickets within your access' : 'Ticket access restricted'];
        $metrics[] = ['label' => 'Transactions', 'value' => $finance['summary']['transactions'] ?? null, 'note' => $finance ? 'Credits and debits in selected period' : 'Revenue access restricted'];
        $metrics[] = ['label' => 'Pending reconciliation', 'value' => $finance['pending_reconciliation'] ?? null, 'note' => ($finance['reconciliation_available'] ?? false) ? 'Credits without a latest matched or reviewed outcome • selected period' : 'Reconciliation access restricted'];
        $metrics[] = ['label' => $lga ? "Today's revenue" : "Today's collections", 'value' => $finance['summary']['today']['net'] ?? null, 'money' => true, 'note' => $finance ? 'Ledger credits minus debits • '.config('ospm.timezone').' today' : 'Revenue access restricted'];
        if ($lga) {
            $metrics[] = ['label' => 'Monthly revenue', 'value' => $finance['summary']['month']['net'] ?? null, 'money' => true, 'note' => $finance ? 'Month to date • ledger credits minus debits' : 'Revenue access restricted'];
        }

        return ['record' => $record->only('id', 'public_id', 'name', 'status'), 'metrics' => $metrics, 'kind' => $lga ? 'lgas' : 'parks', 'as_of' => now()->toIso8601String(), 'finance' => $finance, 'filters' => [...$filters, 'from' => $range['from'], 'to' => $range['to']]];
    }
}
