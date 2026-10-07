<?php

namespace App\Domains\Routes\Queries;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Routes\Models\Route;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class RouteListQuery
{
    public function get(User $user, array $filters): LengthAwarePaginator
    {
        Gate::forUser($user)->authorize('viewAny', Route::class);
        $scopes = app(UserAccessScopeService::class);
        $q = $scopes->scopeRoutes(Route::query(), $user)->withCount(['parks' => fn ($q) => $scopes->scopeParks($q, $user)->where('park_route.status', 'active')]);
        if ($search = $filters['search'] ?? null) {
            $q->where(fn ($q) => $q->where('route_code', 'like', '%'.$search.'%')->orWhere('origin', 'like', '%'.$search.'%')->orWhere('destination', 'like', '%'.$search.'%'));
        }
        if ($status = $filters['status'] ?? null) {
            $q->where('status', $status);
        }
        if ($lga = $filters['lga_id'] ?? null) {
            $q->whereHas('parks', fn ($q) => $scopes->scopeParks($q, $user)->where('lga_id', $lga)->where('park_route.status', 'active'));
        }

        return $q->orderBy($filters['sort'] ?? 'route_code', $filters['direction'] ?? 'asc')->orderBy('id')->paginate(15)->withQueryString()->through(fn ($record) => [...$record->toArray(), 'can_update' => $user->can('update', $record)]);
    }
}
