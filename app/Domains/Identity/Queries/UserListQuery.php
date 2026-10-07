<?php

namespace App\Domains\Identity\Queries;

use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class UserListQuery
{
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);
        $query = User::query()->with('roles:id,name');
        if ($search = $filters['search'] ?? null) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($role = $filters['role'] ?? null) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        return $query->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')->paginate(15)->withQueryString();
    }
}
