<?php

namespace App\Domains\Routes\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Routes\Models\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveRouteAction
{
    public function execute(User $actor, array $data, ?Route $route = null): Route
    {
        return DB::transaction(function () use ($actor, $data, $route) {
            $record = $route ? Route::whereKey($route->id)->lockForUpdate()->firstOrFail() : new Route;
            Gate::forUser($actor)->authorize($route ? 'update' : 'create', $route ? $record : Route::class);
            $before = $record->getAttributes();
            $record->fill(Arr::only($data, ['route_code', 'origin', 'destination', 'description', 'status']));
            if (! $route) {
                $record->created_by = $actor->id;
            }
            $changes = $record->getDirty();
            $record->save();
            activity('routes')->causedBy($actor)->performedOn($record)->withProperties(['before' => Arr::only($before, array_keys($changes)), 'after' => $changes])->log($route ? 'route_updated' : 'route_created');
            if ($route && isset($changes['status'])) {
                activity('routes')->causedBy($actor)->performedOn($record)->withProperties(['from' => $before['status'], 'to' => $changes['status']])->log('route_status_changed');
            }

            return $record;
        });
    }
}
