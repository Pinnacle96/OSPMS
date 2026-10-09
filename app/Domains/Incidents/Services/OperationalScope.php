<?php

namespace App\Domains\Incidents\Services;

use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Enforcement\Models\Violation;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OperationalScope
{
    public function query(Builder $q, User $u, bool $manage = false): Builder
    {
        $scope = app(UserAccessScopeService::class);
        if ($scope->isStatewide($u)) {
            return $q;
        }
        $level = $manage ? AccessLevel::Manage : AccessLevel::View;
        if ($q->getModel() instanceof Violation) {
            return $q->whereHas('inspection', fn ($i) => $this->query($i, $u, $manage));
        }
        if ($q->getModel() instanceof Inspection) {
            return $q->where(function ($q) use ($u, $level) {
                $q->whereHas('ticket', fn ($t) => $t->where(function ($t) use ($u, $level) {
                    foreach (['lgas' => 'lga_id', 'parks' => 'park_id', 'operators' => 'operator_id'] as $relation => $column) {
                        $t->orWhereIn('tickets.'.$column, $this->ids($u, $relation, $level));
                    }
                }))
                    ->orWhere(fn ($q) => $q->whereNull('ticket_id')->whereIn('park_id', $this->parks($u, $level)->select('parks.id')));
            });
        }
        if (! $manage && $u->hasRole('Transport Operator') && ! $u->can('create_incident')) {
            return $q->whereIn('operator_id', $this->ids($u, 'operators', AccessLevel::View));
        }

        return $q->where(function ($q) use ($u, $level, $manage) {
            $q->whereIn('park_id', $this->parks($u, $level)->select('parks.id'));
            // Operator viewers see only their named incidents, not other incidents in a shared park.
            if (! $manage && $u->hasRole('Transport Operator')) {
                $q->orWhereIn('operator_id', $this->ids($u, 'operators', AccessLevel::View));
            }
        });
    }

    private function ids(User $u, string $relation, AccessLevel $level): Builder
    {
        $q = $u->{$relation}()->withTrashed();
        if ($level === AccessLevel::Manage) {
            $q->wherePivot('access_level', 'manage');
        }

        return $q->select($relation.'.id')->getQuery();
    }

    public function parks(User $u, AccessLevel $level = AccessLevel::View): Builder
    {
        $q = Park::withTrashed();
        if (app(UserAccessScopeService::class)->isStatewide($u)) {
            return $q;
        }

        return $q->where(fn ($p) => $p->whereIn('parks.id', $this->ids($u, 'parks', $level))->orWhereIn('parks.lga_id', $this->ids($u, 'lgas', $level)));
    }

    public function allowed(User $u, Model $r, bool $manage = false): bool
    {
        return $this->query($r->newQuery(), $u, $manage)->whereKey($r->id)->exists();
    }
}
