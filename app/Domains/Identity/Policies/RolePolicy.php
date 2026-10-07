<?php

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('manage_roles');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can('manage_roles');
    }
}
