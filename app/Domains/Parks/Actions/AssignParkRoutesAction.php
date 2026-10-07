<?php

namespace App\Domains\Parks\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Parks\Models\Park;
use App\Domains\Routes\Models\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignParkRoutesAction
{
    public function execute(User $actor, Park $park, array $routeIds): void
    {
        DB::transaction(function () use ($actor, $park, $routeIds) {
            $record = Park::whereKey($park->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('assignRoutes', $record);
            $ids = array_map('intval', $routeIds);
            $routes = Route::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if (count(array_unique($ids)) !== count($ids) || $routes->count() !== count($ids) || $routes->contains(fn ($route) => $route->status->value !== 'active')) {
                throw ValidationException::withMessages(['route_ids' => 'Select distinct, active routes that have not been archived.']);
            }
            $before = $record->routes()->wherePivot('status', 'active')->pluck('routes.id')->all();
            DB::table('park_route')->where('park_id', $record->id)->update(['status' => 'inactive']);
            foreach ($ids as $id) {
                $existing = DB::table('park_route')->where('park_id', $record->id)->where('route_id', $id)->first();
                if ($existing) {
                    DB::table('park_route')->where('id', $existing->id)->update(['status' => 'active']);
                } else {
                    DB::table('park_route')->insert(['park_id' => $record->id, 'route_id' => $id, 'status' => 'active', 'created_at' => now()]);
                }
            }
            activity('parks')->causedBy($actor)->performedOn($record)->withProperties(['before' => $before, 'after' => $ids])->log('park_routes_changed');
        });
    }
}
