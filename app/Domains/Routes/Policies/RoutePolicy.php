<?php

namespace App\Domains\Routes\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Routes\Models\Route;

class RoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_route');
    }

    public function view(User $user, Route $route): bool
    {
        return $this->viewAny($user) && app(UserAccessScopeService::class)->scopeRoutes(Route::query(), $user)->whereKey($route->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('manage_route') && app(UserAccessScopeService::class)->isStatewide($user);
    }

    public function update(User $user, Route $route): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Route $route): bool
    {
        return $this->update($user, $route);
    }
}
