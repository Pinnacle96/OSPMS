<?php

namespace App\Domains\Reconciliation\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Reconciliation\Models\ReconciliationItem;
use Illuminate\Database\Eloquent\Builder;

class ReconciliationAccess
{
    public function items(Builder $query, User $user): Builder
    {
        $scope = app(UserAccessScopeService::class);

        return $scope->isStatewide($user) ? $query : $query->whereHas('ticket', fn ($tickets) => $scope->scopeTickets($tickets, $user));
    }

    public function runs(Builder $query, User $user): Builder
    {
        return $user->can('access_statewide') ? $query : $query->whereHas('items', fn ($items) => $this->items($items, $user));
    }

    public function canViewItem(User $user, ReconciliationItem $item): bool
    {
        return $user->can('view_reconciliation') && $this->items(ReconciliationItem::query(), $user)->whereKey($item->id)->exists();
    }
}
