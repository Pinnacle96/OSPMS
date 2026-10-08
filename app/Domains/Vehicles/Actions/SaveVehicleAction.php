<?php

namespace App\Domains\Vehicles\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Vehicles\Models\Vehicle;
use App\Support\References\ReferenceGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveVehicleAction
{
    public function execute(User $actor, array $data, ?Vehicle $record = null): Vehicle
    {
        return DB::transaction(function () use ($actor, $data, $record) {
            $r = $record ? Vehicle::whereKey($record->id)->lockForUpdate()->firstOrFail() : new Vehicle;
            Gate::forUser($actor)->authorize($record ? 'update' : 'create', $record ? $r : Vehicle::class);
            $old = $r->getRawOriginal('status');
            $next = $data['status'];
            if ($next === 'active' && $old !== $next) {
                abort_unless($actor->can('approve_vehicle'), 403);
            }
            if (in_array($next, ['suspended', 'blacklisted'], true) && $old !== $next) {
                abort_unless($actor->can('suspend_vehicle'), 403);
            }
            $r->fill(Arr::only($data, ['registration_number', 'vehicle_type', 'make', 'model', 'colour', 'manufacture_year', 'owner_name', 'owner_phone', 'roadworthiness_expiry', 'insurance_expiry', 'status']));
            if (! $record) {
                $r->vehicle_number = app(ReferenceGenerator::class)->generate('VEH');
                $r->created_by = $actor->id;
                $r->registered_at = now();
            }
            if ($next === 'active' && $old !== $next) {
                $r->approved_at ??= now();
                $r->approved_by = $actor->id;
            }
            $r->save();
            activity('vehicles')->causedBy($actor)->performedOn($r)->withProperties(['from' => $old, 'to' => $next])->log($record ? 'vehicle_updated' : 'vehicle_created');
            if ($old !== $next) {
                activity('vehicles')->causedBy($actor)->performedOn($r)->log('vehicle_'.$next);
            }

            return $r;
        });
    }
}
