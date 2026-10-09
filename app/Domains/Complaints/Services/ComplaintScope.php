<?php

namespace App\Domains\Complaints\Services;

use App\Domains\Complaints\Models\Complaint;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Incidents\Services\OperationalScope;
use Illuminate\Database\Eloquent\Builder;

class ComplaintScope
{
    public function query(Builder $q, User $u): Builder
    {
        if (app(UserAccessScopeService::class)->isStatewide($u)) {
            return $q;
        }
        if ($u->hasRole('Transport Operator') && ! $u->can('manage_complaint')) {
            return $q->whereIn('operator_id', $u->operators()->withTrashed()->select('operators.id'));
        }

        return $q->where(fn ($q) => $q->whereIn('park_id', app(OperationalScope::class)->parks($u)->select('parks.id'))->orWhere(fn ($q) => $q->whereNull('park_id')->where(fn ($q) => $q->where('submitted_by', $u->id)->orWhere('assigned_to', $u->id))));
    }

    public function allowed(User $u, Complaint $c): bool
    {
        return $this->query(Complaint::query(), $u)->whereKey($c->id)->exists();
    }

    public function canAssign(User $u, Complaint $c): bool
    {
        return $u->status->value === 'active' && $u->can('manage_complaint') && (! $c->park_id || $this->allowed($u, $c));
    }
}
