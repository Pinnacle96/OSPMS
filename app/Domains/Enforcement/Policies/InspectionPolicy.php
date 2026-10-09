<?php

namespace App\Domains\Enforcement\Policies;

use App\Domains\Identity\Models\User;

class InspectionPolicy
{
    public function create(User $u): bool
    {
        return $u->can('access_field') && $u->can('record_inspection');
    }
}
