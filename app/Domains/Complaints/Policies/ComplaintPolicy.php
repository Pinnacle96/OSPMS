<?php

namespace App\Domains\Complaints\Policies;

use App\Domains\Complaints\Models\Complaint;
use App\Domains\Complaints\Services\ComplaintScope;
use App\Domains\Identity\Models\User;

class ComplaintPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_complaint');
    }

    public function create(User $u): bool
    {
        return $u->can('create_complaint');
    }

    public function view(User $u, Complaint $c): bool
    {
        return $u->can('view_complaint') && app(ComplaintScope::class)->allowed($u, $c);
    }

    public function manage(User $u, Complaint $c): bool
    {
        return $u->can('manage_complaint') && $this->view($u, $c);
    }

    public function viewEvidence(User $u, Complaint $c): bool
    {
        return $this->view($u, $c) && ! $this->external($u);
    }

    public function contacts(User $u, Complaint $c): bool
    {
        return $this->view($u, $c) && $u->can('view_complaint_contacts') && ! $this->external($u);
    }

    public function evidence(User $u, Complaint $c): bool
    {
        return $this->manage($u, $c) && $u->can('upload_evidence') && ! in_array($c->status->value, ['resolved', 'closed']);
    }

    public function external(User $u): bool
    {
        return $u->hasRole('Transport Operator') && ! $u->can('manage_complaint');
    }
}
