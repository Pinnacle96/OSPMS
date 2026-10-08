<?php

namespace App\Domains\Assignments\Policies;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;

class AssignmentPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_assignment');
    }

    public function view(User $u, DriverAssignment $r): bool
    {
        return $this->viewAny($u) && app(UserAccessScopeService::class)->scopeAssignments(DriverAssignment::query(), $u)->whereKey($r->id)->exists();
    }

    public function create(User $u): bool
    {
        return $u->can('manage_assignment');
    }

    public function end(User $u, DriverAssignment $r): bool
    {
        return $this->create($u) && app(UserAccessScopeService::class)->scopeAssignments(DriverAssignment::query(), $u, AccessLevel::Manage)->whereKey($r->id)->exists();
    }
}
