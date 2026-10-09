<?php

namespace App\Domains\Drivers\Actions;

use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\RecordNotificationService;
use App\Notifications\DriverApprovedNotification;
use App\Support\References\ReferenceGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveDriverAction
{
    public function execute(User $actor, array $data, ?Driver $record = null): Driver
    {
        return DB::transaction(function () use ($actor, $data, $record) {
            $r = $record ? Driver::whereKey($record->id)->lockForUpdate()->firstOrFail() : new Driver;
            Gate::forUser($actor)->authorize($record ? 'update' : 'create', $record ? $r : Driver::class);
            $old = $r->getRawOriginal('status');
            $next = $data['status'];
            if ($next === 'active' && $old !== $next) {
                abort_unless($actor->can('approve_driver'), 403);
            }
            if (in_array($next, ['suspended', 'blacklisted'], true) && $old !== $next) {
                abort_unless($actor->can('suspend_driver'), 403);
            }
            $r->fill(Arr::only($data, ['first_name', 'middle_name', 'last_name', 'phone', 'email', 'residential_address', 'licence_number', 'licence_expiry', 'emergency_contact_name', 'emergency_contact_phone', 'next_of_kin', 'status']));
            if (! $record) {
                $r->driver_number = app(ReferenceGenerator::class)->generate('DRV');
                $r->created_by = $actor->id;
                $r->registered_at = now();
            }
            if ($next === 'active' && $old !== $next) {
                $r->approved_at ??= now();
                $r->approved_by = $actor->id;
            }
            $r->save();
            activity('drivers')->causedBy($actor)->performedOn($r)->withProperties(['from' => $old, 'to' => $next])->log($record ? 'driver_updated' : 'driver_created');
            if ($old !== $next) {
                activity('drivers')->causedBy($actor)->performedOn($r)->log('driver_'.$next);
            }

            if ($next === 'active' && $old !== $next) {
                app(RecordNotificationService::class)->send(User::find($r->created_by), $r, 'driver', 'approval:'.$r->approved_at->toIso8601String(), $r->driver_number, 'Driver registration approved', DriverApprovedNotification::class);
            }

            return $r;
        });
    }
}
