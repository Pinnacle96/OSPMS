<?php

namespace App\Domains\Incidents\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Models\Incident;
use App\Domains\Incidents\Services\OperationalScope;

class IncidentPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_incident');
    }

    public function create(User $u): bool
    {
        return $u->can('create_incident');
    }

    public function view(User $u, Incident $r): bool
    {
        return $u->can('view_incident') && app(OperationalScope::class)->allowed($u, $r);
    }

    public function manage(User $u, Incident $r): bool
    {
        return $u->can('manage_incident') && app(OperationalScope::class)->allowed($u, $r, true);
    }

    public function evidence(User $u, Incident $r): bool
    {
        return $this->view($u, $r) && $u->can('upload_evidence') && ! in_array($r->status->value, ['resolved', 'closed']);
    }
}
