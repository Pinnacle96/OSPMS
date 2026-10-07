<?php

namespace App\Domains\Geography\Policies;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;

class LgaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_lga');
    }

    public function view(User $user, Lga $lga): bool
    {
        return $this->viewAny($user) && app(UserAccessScopeService::class)->canAccessLga($user, $lga);
    }

    public function create(User $user): bool
    {
        return $user->can('manage_lga') && app(UserAccessScopeService::class)->isStatewide($user);
    }

    public function update(User $user, Lga $lga): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Lga $lga): bool
    {
        return $this->update($user, $lga);
    }
}
