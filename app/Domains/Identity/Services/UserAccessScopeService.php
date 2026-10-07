<?php

namespace App\Domains\Identity\Services;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use Illuminate\Database\Eloquent\Builder;

class UserAccessScopeService
{
    public function isStatewide(User $user): bool
    {
        return $user->can('access_statewide');
    }

    public function scopeLgas(Builder $query, User $user, AccessLevel $level = AccessLevel::View): Builder
    {
        if ($this->isStatewide($user)) {
            return $query;
        }

        return $query->whereIn('lgas.id', $this->ids($user, 'lgas', $level));
    }

    public function scopeParks(Builder $query, User $user, AccessLevel $level = AccessLevel::View): Builder
    {
        if ($this->isStatewide($user)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user, $level) {
            $q->whereIn('parks.id', $this->ids($user, 'parks', $level))
                ->orWhereIn('parks.lga_id', $this->ids($user, 'lgas', $level));
        });
    }

    public function scopeOperators(Builder $query, User $user, AccessLevel $level = AccessLevel::View): Builder
    {
        // Operator-to-park relationships are introduced at Milestone 4. Until then, fail closed to explicit operator access.
        return $this->isStatewide($user) ? $query : $query->whereIn('operators.id', $this->ids($user, 'operators', $level));
    }

    public function canAccessLga(User $user, Lga $lga, AccessLevel $level = AccessLevel::View): bool
    {
        return $this->scopeLgas(Lga::query(), $user, $level)->whereKey($lga->id)->exists();
    }

    public function canAccessPark(User $user, Park $park, AccessLevel $level = AccessLevel::View): bool
    {
        return $this->scopeParks(Park::query(), $user, $level)->whereKey($park->id)->exists();
    }

    public function canAccessOperator(User $user, Operator $operator, AccessLevel $level = AccessLevel::View): bool
    {
        return $this->scopeOperators(Operator::query(), $user, $level)->whereKey($operator->id)->exists();
    }

    public function accessibleLgaIds(User $user): array
    {
        return $this->scopeLgas(Lga::query(), $user)->pluck('id')->all();
    }

    public function accessibleParkIds(User $user): array
    {
        return $this->scopeParks(Park::query(), $user)->pluck('id')->all();
    }

    public function accessibleOperatorIds(User $user): array
    {
        return $this->scopeOperators(Operator::query(), $user)->pluck('id')->all();
    }

    public function summary(User $user): array
    {
        $map = fn ($models) => $models->map(fn ($model) => ['id' => $model->id, 'name' => $model->name, 'access_level' => $model->pivot->access_level])->values()->all();

        return ['statewide' => $this->isStatewide($user), 'lgas' => $map($user->lgas), 'parks' => $map($user->parks), 'operators' => $map($user->operators)];
    }

    private function ids(User $user, string $relation, AccessLevel $level): Builder
    {
        $query = $user->{$relation}();
        if ($level === AccessLevel::Manage) {
            $query->wherePivot('access_level', AccessLevel::Manage->value);
        }

        return $query->select($relation.'.id')->getQuery();
    }
}
