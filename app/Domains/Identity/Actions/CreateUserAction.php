<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateUserAction
{
    public function execute(User $actor, array $data): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        return DB::transaction(function () use ($actor, $data) {
            $user = User::create(collect($data)->except('roles')->all());
            if (! empty($data['roles'])) {
                Gate::forUser($actor)->authorize('manage_roles');
                $user->syncRoles($data['roles']);
                activity()->causedBy($actor)->performedOn($user)->withProperties(['roles' => $data['roles']])->log('user_roles_assigned');
            }

            return $user;
        });
    }
}
