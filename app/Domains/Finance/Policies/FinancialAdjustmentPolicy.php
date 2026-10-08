<?php

namespace App\Domains\Finance\Policies;

use App\Domains\Finance\Models\FinancialAdjustment;
use App\Domains\Finance\Models\FinancialTransaction;
use App\Domains\Identity\Models\User;

class FinancialAdjustmentPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_adjustment') && $u->can('access_statewide');
    }

    public function view(User $u, FinancialAdjustment $a): bool
    {
        return $this->viewAny($u) && $a->originalTransaction && $u->can('view', $a->originalTransaction);
    }

    public function create(User $u): bool
    {
        return $u->can('request_adjustment') && $u->can('access_statewide');
    }

    public function request(User $u, FinancialTransaction $t): bool
    {
        return $this->create($u) && $u->can('view', $t);
    }

    public function approve(User $u, FinancialAdjustment $a): bool
    {
        return $this->view($u, $a) && $u->can('approve_adjustment') && $u->id !== $a->requested_by;
    }
}
