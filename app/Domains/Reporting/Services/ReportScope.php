<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Incidents\Services\OperationalScope;
use App\Domains\Parks\Models\Park;
use Illuminate\Database\Eloquent\Builder;

class ReportScope
{
    public function own(User $u): bool
    {
        return $u->hasRole('Transport Operator') && ! $u->can('access_statewide');
    }

    public function operators(User $u): Builder
    {
        return $u->operators()->withTrashed()->select('operators.id')->getQuery();
    }

    public function parks(User $u): Builder
    {
        if ($this->own($u)) {
            return Park::withTrashed()->whereHas('operators', fn ($o) => $o->whereIn('operators.id', $this->operators($u))->where('operator_park.status', 'active'));
        }

        return app(OperationalScope::class)->parks($u);
    }

    public function assignments(User $u): Builder
    {
        $q = DriverAssignment::query();
        if ($this->own($u)) {
            return $q->whereIn('operator_id', $this->operators($u));
        }
        if ($u->can('access_statewide')) {
            return $q;
        }

        return $q->where(fn ($q) => $q->whereIn('park_id', $this->parks($u)->select('parks.id'))->orWhereIn('operator_id', $this->operators($u)));
    }

    public function tickets(Builder $q, User $u): Builder
    {
        return $this->own($u) ? $q->whereIn('operator_id', $this->operators($u)) : app(UserAccessScopeService::class)->scopeTickets($q, $u);
    }

    public function registry(Builder $q, User $u, string $family): Builder
    {
        if ($u->can('access_statewide')) {
            return $q;
        }
        if ($family === 'operators') {
            if ($this->own($u)) {
                return $q->whereIn('operators.id', $this->operators($u));
            }

            return $q->where(fn ($q) => $q->whereIn('operators.id', $this->operators($u))->orWhereHas('parks', fn ($p) => $p->withTrashed()->whereIn('parks.id', $this->parks($u)->select('parks.id'))->where('operator_park.status', 'active')));
        }

        return $q->where(function ($q) use ($u, $family) {
            $q->whereIn($family.'.id', $this->assignments($u)->select($family === 'drivers' ? 'driver_id' : 'vehicle_id'));
            if (! $this->own($u)) {
                $q->orWhere(fn ($q) => $q->where('created_by', $u->id)->whereDoesntHave('assignments'));
            }
        });
    }

    public function signature(User $u): string
    {
        $u = User::findOrFail($u->id);
        $data = ['permissions' => $u->getAllPermissions()->pluck('name')->sort()->values()->all(), 'roles' => $u->getRoleNames()->sort()->values()->all()];
        foreach (['lgas', 'parks', 'operators'] as $rel) {
            $data[$rel] = $u->$rel()->withTrashed()->orderBy($rel.'.id')->get()->map(fn ($r) => [$r->id, $r->pivot->access_level])->all();
        }

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
