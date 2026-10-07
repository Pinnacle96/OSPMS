<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;
use App\Domains\Routes\Models\Route;
use Illuminate\Support\Facades\Gate;

class LocalDashboardQuery
{
    public function get(User $user, Lga|Park $record): array
    {
        Gate::forUser($user)->authorize('view', $record);
        $lga = $record instanceof Lga;
        $parks = app(UserAccessScopeService::class)->scopeParks(Park::query(), $user);
        if ($lga) {
            $parks->where('lga_id', $record->id);
        } else {
            $parks->whereKey($record->id);
        }
        $routes = Route::query()->where('status', 'active')->whereHas('parks', fn ($q) => $q->whereIn('parks.id', (clone $parks)->select('parks.id'))->where('park_route.status', 'active'))->count();
        $metrics = $lga ? [['label' => 'Total parks', 'value' => $parks->count(), 'note' => 'Within this LGA'], ['label' => 'Active parks', 'value' => (clone $parks)->where('status', 'active')->count(), 'note' => 'Operational status is active']] : [];
        $metrics[] = ['label' => 'Approved routes', 'value' => $routes, 'note' => 'Active routes and assignments'];
        foreach (['Registered operators', 'Registered vehicles', 'Registered drivers', 'Tickets', 'Transactions', 'Pending reconciliation'] as $label) {
            $metrics[] = ['label' => $label, 'value' => 0, 'note' => 'Workflow not yet enabled'];
        }
        $metrics[] = ['label' => $lga ? "Today's revenue" : "Today's collections", 'value' => '0.00', 'money' => true, 'note' => 'Collections not yet enabled'];
        if ($lga) {
            $metrics[] = ['label' => 'Monthly revenue', 'value' => '0.00', 'money' => true, 'note' => 'Collections not yet enabled'];
        }

        return ['record' => $record->only('id', 'public_id', 'name', 'status'), 'metrics' => $metrics, 'kind' => $lga ? 'lgas' : 'parks', 'as_of' => now()->toIso8601String()];
    }
}
