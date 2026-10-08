<?php

namespace App\Domains\Operators\Policies;

use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;

class OperatorPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('view_operator');
    }

    public function view(User $u, Operator $r): bool
    {
        return $this->viewAny($u) && app(UserAccessScopeService::class)->scopeOperators(Operator::query(), $u)->whereKey($r->id)->exists();
    }

    public function create(User $u): bool
    {
        return $u->can('create_operator') && (app(UserAccessScopeService::class)->isStatewide($u) || app(UserAccessScopeService::class)->scopeParks(Park::query(), $u, AccessLevel::Manage)->exists());
    }

    public function update(User $u, Operator $r): bool
    {
        $s = app(UserAccessScopeService::class);
        if (! $u->can('edit_operator') || ! $s->scopeOperators(Operator::query(), $u, AccessLevel::Manage)->whereKey($r->id)->exists()) {
            return false;
        }
        if ($s->isStatewide($u)) {
            return true;
        }

        // Editing a shared master affects every relationship: fail closed if any geography is unmanaged.
        return ! $r->parks()->whereNotIn('parks.id', $s->scopeParks(Park::query(), $u, AccessLevel::Manage)->select('parks.id'))->exists();
    }

    public function approve(User $u, Operator $r): bool
    {
        return $u->can('approve_operator') && $this->update($u, $r);
    }

    public function suspend(User $u, Operator $r): bool
    {
        return $u->can('suspend_operator') && $this->update($u, $r);
    }
}
