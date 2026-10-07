<?php

namespace App\Domains\Parks\Queries;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ParkListQuery
{
    public function get(User $user, array $filters): LengthAwarePaginator
    {
        Gate::forUser($user)->authorize('viewAny', Park::class);
        $q = app(UserAccessScopeService::class)->scopeParks(Park::query(), $user)->with('lga:id,public_id,name')->withCount(['routes' => fn ($q) => $q->where('routes.status', 'active')->where('park_route.status', 'active')]);
        if ($search = $filters['search'] ?? null) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('park_code', 'like', '%'.$search.'%')->orWhere('address', 'like', '%'.$search.'%'));
        }
        if ($status = $filters['status'] ?? null) {
            $q->where('status', $status);
        }
        if ($lga = $filters['lga_id'] ?? null) {
            $q->where('lga_id', $lga);
        }

        return $q->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')->orderBy('id')->paginate(15)->withQueryString()->through(fn ($record) => [...$record->toArray(), 'can_update' => $user->can('update', $record)]);
    }
}
