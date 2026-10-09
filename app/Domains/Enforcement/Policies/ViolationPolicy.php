<?php

namespace App\Domains\Enforcement\Policies;

use App\Domains\Enforcement\Models\Violation;
use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Services\OperationalScope;

class ViolationPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_violation');
    }

    public function create(User $u): bool
    {
        return $u->can('record_violation');
    }

    public function view(User $u, Violation $r): bool
    {
        return $u->can('view_violation') && app(OperationalScope::class)->allowed($u, $r);
    }

    public function resolve(User $u, Violation $r): bool
    {
        return $u->can('resolve_violation') && app(OperationalScope::class)->allowed($u, $r, true);
    }

    public function evidence(User $u, Violation $r): bool
    {
        return $this->view($u, $r) && $u->can('upload_evidence') && $r->status->value === 'open';
    }
}
