<?php

namespace App\Domains\Revenue\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Revenue\Models\RevenueHead;

class RevenueHeadPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_revenue_head') && $u->can('access_statewide');
    }

    public function view(User $u, RevenueHead $r): bool
    {
        return $this->viewAny($u);
    }

    public function create(User $u): bool
    {
        return $u->can('manage_revenue_head') && $u->can('access_statewide');
    }

    public function update(User $u, RevenueHead $r): bool
    {
        return $this->create($u);
    }
}
