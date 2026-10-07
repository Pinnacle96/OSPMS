<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AssignUserScopeAction
{
    public function execute(User $actor, User $user, array $data): void
    {
        Gate::forUser($actor)->authorize('assignScopes', $user);
        DB::transaction(function () use ($actor, $user, $data) {
            foreach (['lgas', 'parks', 'operators'] as $relation) {
                $pivots = collect($data[$relation] ?? [])->mapWithKeys(fn ($scope) => [$scope['id'] => ['access_level' => $scope['access_level'], 'created_at' => now()]])->all();
                $user->{$relation}()->sync($pivots);
            }
            activity()->causedBy($actor)->performedOn($user)->withProperties($data)->log('user_scopes_changed');
        });
    }
}
