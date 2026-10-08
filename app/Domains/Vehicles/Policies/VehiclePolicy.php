<?php

namespace App\Domains\Vehicles\Policies;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;
use App\Domains\Vehicles\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_vehicle');
    }

    public function view(User $u, Vehicle $r): bool
    {
        return $this->viewAny($u) && app(UserAccessScopeService::class)->scopeParticipants(Vehicle::query(), $u)->whereKey($r->id)->exists();
    }

    public function create(User $u): bool
    {
        return $u->can('create_vehicle') && (app(UserAccessScopeService::class)->isStatewide($u) || app(UserAccessScopeService::class)->scopeParks(Park::query(), $u, AccessLevel::Manage)->exists());
    }

    public function update(User $u, Vehicle $r): bool
    {
        $s = app(UserAccessScopeService::class);
        if (! $u->can('edit_vehicle') || ! $s->scopeParticipants(Vehicle::query(), $u, AccessLevel::Manage)->whereKey($r->id)->exists()) {
            return false;
        }
        if ($s->isStatewide($u)) {
            return true;
        }

        // Editing a shared master affects every relationship: fail closed if any geography is unmanaged.
        return ! $r->assignments()->whereNotIn('id', $s->scopeAssignments(DriverAssignment::query(), $u, AccessLevel::Manage)->select('id'))->exists();
    }

    public function approve(User $u, Vehicle $r): bool
    {
        return $u->can('approve_vehicle') && $this->update($u, $r);
    }

    public function suspend(User $u, Vehicle $r): bool
    {
        return $u->can('suspend_vehicle') && $this->update($u, $r);
    }
}
