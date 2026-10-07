<?php

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('manage_users');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('manage_users');
    }

    public function create(User $actor): bool
    {
        return $actor->can('manage_users');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can('manage_users');
    }

    public function assignScopes(User $actor, User $user): bool
    {
        return $actor->can('manage_users');
    }
}
