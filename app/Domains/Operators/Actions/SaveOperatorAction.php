<?php

namespace App\Domains\Operators\Actions;

use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Operators\Models\OperatorPark;
use App\Domains\Operators\Models\OperatorRoute;
use App\Domains\Parks\Models\Park;
use App\Support\References\ReferenceGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveOperatorAction
{
    public function execute(User $actor, array $data, ?Operator $operator = null): Operator
    {
        return DB::transaction(function () use ($actor, $data, $operator) {
            $r = $operator ? Operator::whereKey($operator->id)->lockForUpdate()->firstOrFail() : new Operator;
            Gate::forUser($actor)->authorize($operator ? 'update' : 'create', $operator ? $r : Operator::class);
            $old = $r->getRawOriginal('status');
            $next = $data['status'];
            if ($next === 'approved' && $old !== $next) {
                abort_unless($actor->can('approve_operator'), 403);
            }
            if ($next === 'suspended' && $old !== $next) {
                abort_unless($actor->can('suspend_operator'), 403);
            }
            $parks = array_values(array_unique($data['park_ids']));
            $scope = app(UserAccessScopeService::class);
            foreach ($parks as $id) {
                $park = Park::whereKey($id)->lockForUpdate()->firstOrFail();
                abort_unless($scope->canAccessPark($actor, $park, AccessLevel::Manage), 403);
                $existingPark = $r->exists && $r->parks()->where('parks.id', $park->id)->wherePivot('status', 'active')->exists();
                if ($park->status->value !== 'active' && ! $existingPark) {
                    throw ValidationException::withMessages(['park_ids' => 'Select active parks only.']);
                }
            }
            foreach ($data['route_keys'] ?? [] as $key) {
                [$parkId,$routeId] = array_map('intval', explode(':', $key));
                $existingRoute = $r->exists && OperatorRoute::where('operator_id', $r->id)->where('park_id', $parkId)->where('route_id', $routeId)->where('status', 'active')->exists();
                if (! in_array($parkId, array_map('intval', $parks), true) || (! $existingRoute && ! Park::findOrFail($parkId)->routes()->where('routes.id', $routeId)->where('routes.status', 'active')->wherePivot('status', 'active')->exists())) {
                    throw ValidationException::withMessages(['route_keys' => 'Every route must be approved for a selected park.']);
                }
            }
            $r->fill(Arr::only($data, ['name', 'registration_number', 'contact_person', 'phone', 'email', 'address', 'status']));
            if (! $operator) {
                $r->operator_number = app(ReferenceGenerator::class)->generate('OPR');
                $r->created_by = $actor->id;
                $r->registered_at = now();
            }
            if ($next === 'approved' && $old !== $next) {
                $r->approved_at ??= now();
                $r->approved_by = $actor->id;
            }
            $r->save();
            OperatorPark::where('operator_id', $r->id)->update(['status' => 'inactive']);
            foreach ($parks as $id) {
                $link = OperatorPark::firstOrNew(['operator_id' => $r->id, 'park_id' => $id]);
                $link->status = 'active';
                $link->created_at ??= now();
                if ($next === 'approved') {
                    $link->approved_at ??= now();
                } $link->save();
            }
            OperatorRoute::where('operator_id', $r->id)->update(['status' => 'inactive']);
            foreach ($data['route_keys'] ?? [] as $key) {
                [$parkId,$routeId] = array_map('intval', explode(':', $key));
                $link = OperatorRoute::firstOrNew(['operator_id' => $r->id, 'park_id' => $parkId, 'route_id' => $routeId]);
                $link->status = 'active';
                $link->created_at ??= now();
                if ($next === 'approved') {
                    $link->approved_at ??= now();
                } $link->save();
            }
            activity('operators')->causedBy($actor)->performedOn($r)->withProperties(['from' => $old, 'to' => $next, 'park_ids' => $parks, 'route_keys' => $data['route_keys'] ?? []])->log($operator ? 'operator_updated' : 'operator_created');
            if ($old !== $next) {
                activity('operators')->causedBy($actor)->performedOn($r)->log('operator_'.$next);
            }

            return $r;
        });
    }
}
