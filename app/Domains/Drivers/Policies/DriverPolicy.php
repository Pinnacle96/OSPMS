<?php

namespace App\Domains\Drivers\Policies;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;

class DriverPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_driver');
    }

    public function view(User $u, Driver $r): bool
    {
        return $this->viewAny($u) && app(UserAccessScopeService::class)->scopeParticipants(Driver::query(), $u)->whereKey($r->id)->exists();
    }

    public function create(User $u): bool
    {
        return $u->can('create_driver') && (app(UserAccessScopeService::class)->isStatewide($u) || app(UserAccessScopeService::class)->scopeParks(Park::query(), $u, AccessLevel::Manage)->exists());
    }

    public function update(User $u, Driver $r): bool
    {
        $s = app(UserAccessScopeService::class);
        if (! $u->can('edit_driver') || ! $s->scopeParticipants(Driver::query(), $u, AccessLevel::Manage)->whereKey($r->id)->exists()) {
            return false;
        }
        if ($s->isStatewide($u)) {
            return true;
        }

        // Editing a shared master affects every relationship: fail closed if any geography is unmanaged.
        return ! $r->assignments()->whereNotIn('id', $s->scopeAssignments(DriverAssignment::query(), $u, AccessLevel::Manage)->select('id'))->exists();
    }

    public function approve(User $u, Driver $r): bool
    {
        return $u->can('approve_driver') && $this->update($u, $r);
    }

    public function suspend(User $u, Driver $r): bool
    {
        return $u->can('suspend_driver') && $this->update($u, $r);
    }
}
