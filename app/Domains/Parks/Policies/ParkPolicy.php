<?php

namespace App\Domains\Parks\Policies;

use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;

class ParkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_park');
    }

    public function view(User $user, Park $park): bool
    {
        return $this->viewAny($user) && app(UserAccessScopeService::class)->canAccessPark($user, $park);
    }

    public function create(User $user): bool
    {
        return $user->can('create_park') && app(UserAccessScopeService::class)->isStatewide($user);
    }

    public function update(User $user, Park $park): bool
    {
        return $user->can('manage_park') && app(UserAccessScopeService::class)->canAccessPark($user, $park, AccessLevel::Manage);
    }

    public function assignRoutes(User $user, Park $park): bool
    {
        return $user->can('assign_park_routes') && $this->update($user, $park);
    }

    public function delete(User $user, Park $park): bool
    {
        return $this->update($user, $park) && app(UserAccessScopeService::class)->isStatewide($user);
    }
}
