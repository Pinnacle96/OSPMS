<?php

namespace App\Domains\System\Queries;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\System\Services\CatalogDefinition;
use Illuminate\Support\Facades\Gate;

class CatalogListQuery
{
    public const SEARCH = ['operators' => ['name', 'operator_number', 'phone', 'registration_number'], 'drivers' => ['first_name', 'middle_name', 'last_name', 'phone', 'driver_number', 'licence_number'], 'vehicles' => ['registration_number', 'vehicle_number', 'make', 'model'], 'revenue-heads' => ['code', 'name'], 'fee-configurations' => ['public_id', 'amount']];

    public function get(User $u, string $kind, array $filters)
    {
        $model = app(CatalogDefinition::class)->model($kind);
        Gate::forUser($u)->authorize('viewAny', $model);
        $q = $model::query();
        $s = app(UserAccessScopeService::class);
        if ($kind === 'operators') {
            $s->scopeOperators($q, $u);
        }
        if (in_array($kind, ['drivers', 'vehicles'], true)) {
            $s->scopeParticipants($q, $u);
        }
        if (! empty($filters['search'])) {
            $q->where(function ($q) use ($kind, $filters) {
                foreach (self::SEARCH[$kind] as $column) {
                    $q->orWhere($column, 'like', '%'.$filters['search'].'%');
                }
            });
        }
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if ($kind === 'vehicles' && ! empty($filters['vehicle_type'])) {
            $q->where('vehicle_type', $filters['vehicle_type']);
        }
        foreach (['park_id', 'operator_id', 'lga_id', 'revenue_head_id'] as $column) {
            if (! empty($filters[$column])) {
                if ($kind === 'fee-configurations') {
                    $q->where($column, $filters[$column]);
                } elseif ($kind === 'operators' && $column === 'park_id') {
                    $q->whereHas('parks', fn ($p) => $s->scopeParks($p, $u)->where('parks.id', $filters[$column]));
                } elseif (in_array($kind, ['drivers', 'vehicles'], true) && in_array($column, ['park_id', 'operator_id'])) {
                    $q->whereHas('assignments', fn ($a) => $s->scopeAssignments($a, $u)->where($column, $filters[$column]));
                }
            }
        }
        if ($kind === 'fee-configurations') {
            $q->with('revenueHead:id,public_id,name');
        }
        $allowed = array_merge(self::SEARCH[$kind], ['created_at', 'status']);
        if ($kind === 'fee-configurations') {
            $allowed[] = 'effective_from';
        }
        $sort = in_array($filters['sort'] ?? '', $allowed, true) ? $filters['sort'] : 'created_at';

        return $q->orderBy($sort, $filters['direction'] ?? 'desc')->orderBy('id', 'desc')->paginate(15)->withQueryString();
    }
}
