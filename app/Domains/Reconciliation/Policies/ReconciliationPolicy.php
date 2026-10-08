<?php

namespace App\Domains\Reconciliation\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Reconciliation\Models\ReconciliationItem;
use App\Domains\Reconciliation\Models\ReconciliationRun;
use App\Domains\Reconciliation\Services\ReconciliationAccess;

class ReconciliationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_reconciliation');
    }

    public function view(User $user, ReconciliationRun|ReconciliationItem $record): bool
    {
        if ($record instanceof ReconciliationItem) {
            return app(ReconciliationAccess::class)->canViewItem($user, $record);
        }

        return $this->viewAny($user) && app(ReconciliationAccess::class)->runs(ReconciliationRun::query(), $user)->whereKey($record->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('reconcile_transaction') && $user->can('view_reconciliation') && $user->can('access_statewide');
    }

    public function resolve(User $user, ReconciliationItem $item): bool
    {
        return $this->view($user, $item) && $user->can('resolve_reconciliation_exception') && $user->can('access_statewide') && in_array($item->status->value, ['exception', 'under_review']);
    }
}
