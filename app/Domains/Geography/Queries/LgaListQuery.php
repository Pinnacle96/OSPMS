<?php

namespace App\Domains\Geography\Queries;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class LgaListQuery
{
    public function get(User $user, array $filters): LengthAwarePaginator
    {
        Gate::forUser($user)->authorize('viewAny', Lga::class);
        $q = app(UserAccessScopeService::class)->scopeLgas(Lga::query(), $user)->withCount('parks');
        if ($search = $filters['search'] ?? null) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'));
        }
        if ($status = $filters['status'] ?? null) {
            $q->where('status', $status);
        }

        return $q->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')->orderBy('id')->paginate(15)->withQueryString()->through(fn ($record) => [...$record->toArray(), 'can_update' => $user->can('update', $record)]);
    }
}
