<?php

namespace App\Domains\Finance\Policies;

use App\Domains\Finance\Models\Settlement;
use App\Domains\Identity\Models\User;

class SettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_settlement') && $user->can('access_statewide');
    }

    public function view(User $user, Settlement $settlement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create_settlement') && $user->can('access_statewide');
    }
}
