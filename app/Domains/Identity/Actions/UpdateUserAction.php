<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UpdateUserAction
{
    public function execute(User $actor, User $user, array $data): void
    {
        Gate::forUser($actor)->authorize('update', $user);
        DB::transaction(function () use ($actor, $user, $data) {
            $attributes = collect($data)->except('roles')->all();
            if (empty($attributes['password'])) {
                unset($attributes['password']);
            }
            $credentialsChanged = isset($attributes['password']) || $attributes['status'] !== UserStatus::Active->value;
            if ($credentialsChanged) {
                $attributes['remember_token'] = Str::random(60);
            }
            // Credential/session fields are deliberately excluded from mass assignment and general audit properties.
            $rememberToken = $attributes['remember_token'] ?? null;
            unset($attributes['remember_token']);
            $user->update($attributes);
            if ($credentialsChanged) {
                $user->forceFill(['remember_token' => $rememberToken])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                activity()->causedBy($actor)->performedOn($user)->log('user_sessions_revoked');
            }
            if (array_key_exists('roles', $data)) {
                Gate::forUser($actor)->authorize('manage_roles');
                $user->syncRoles($data['roles']);
                activity()->causedBy($actor)->performedOn($user)->withProperties(['roles' => $data['roles']])->log('user_roles_changed');
            }
        });
    }
}
