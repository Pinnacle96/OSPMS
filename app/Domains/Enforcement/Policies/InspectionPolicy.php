<?php

namespace App\Domains\Enforcement\Policies;

use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Services\OperationalScope;

class InspectionPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_inspection');
    }

    public function view(User $u, Inspection $r): bool
    {
        return $u->can('view_inspection') && app(OperationalScope::class)->allowed($u, $r);
    }

    public function evidence(User $u, Inspection $r): bool
    {
        return $this->view($u, $r) && $u->can('upload_evidence');
    }

    public function create(User $u): bool
    {
        return $u->can('access_field') && $u->can('record_inspection');
    }
}
