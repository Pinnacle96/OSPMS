<?php

namespace App\Domains\Revenue\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Revenue\Models\FeeConfiguration;

class FeeConfigurationPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_fee_configuration') && $u->can('access_statewide');
    }

    public function view(User $u, FeeConfiguration $r): bool
    {
        return $this->viewAny($u);
    }

    public function create(User $u): bool
    {
        return $u->can('manage_fee_configuration') && $u->can('access_statewide');
    }

    public function update(User $u, FeeConfiguration $r): bool
    {
        return $this->create($u);
    }
}
