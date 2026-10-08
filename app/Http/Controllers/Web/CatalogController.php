<?php

namespace App\Http\Controllers\Web;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Routes\Models\Route;
use App\Domains\System\Queries\CatalogListQuery;
use App\Domains\System\Services\CatalogDefinition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\CatalogFilterRequest;
use App\Http\Requests\Operations\SaveCatalogRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class CatalogController extends Controller
{
    public function index(CatalogFilterRequest $request, CatalogListQuery $query, CatalogDefinition $d)
    {
        $kind = $d->kind($request);

        return Inertia::render($d::PAGES[$kind].'/Index', ['kind' => $kind, 'records' => $query->get($request->user(), $kind, $request->validated()), 'filters' => $request->validated(), 'can_create' => $request->user()->can('create', $d->model($kind)), 'options' => $this->options($request, $kind)]);
    }

    public function create(Request $request, CatalogDefinition $d)
    {
        $kind = $d->kind($request);
        Gate::authorize('create', $d->model($kind));

        return Inertia::render($d::PAGES[$kind].'/Create', ['kind' => $kind, 'options' => $this->options($request, $kind, true)]);
    }

    public function store(SaveCatalogRequest $request, CatalogDefinition $d)
    {
        $kind = $d->kind($request);
        $r = app($d::ACTIONS[$kind])->execute($request->user(), $request->validated());

        return redirect('/'.$kind.'/'.$r->public_id)->with('success', 'Record registered.');
    }

    public function edit(Request $request, string $record, CatalogDefinition $d)
    {
        $kind = $d->kind($request);
        $r = $d->record($kind, $record);
        Gate::authorize('update', $r);
        $extra = $kind === 'operators' ? ['selected_parks' => $r->parks()->wherePivot('status', 'active')->pluck('parks.id'), 'selected_routes' => $r->routes()->wherePivot('status', 'active')->get()->map(fn ($route) => $route->pivot->park_id.':'.$route->id)] : [];

        $options = $this->options($request, $kind, true);
        if ($kind === 'operators') {
            $parkNames = $r->parks()->pluck('name', 'parks.id');
            $retained = $r->routes()->wherePivot('status', 'active')->get()->map(fn ($route) => [
                'id' => $route->pivot->park_id.':'.$route->id,
                'name' => ($parkNames[$route->pivot->park_id] ?? 'Park').' — '.$route->origin.' → '.$route->destination.' (retained)',
            ]);
            $options['operator_routes'] = collect($options['operator_routes'])->merge($retained)->unique('id')->values();
        }

        return Inertia::render($d::PAGES[$kind].'/Edit', ['kind' => $kind, 'record' => $r, 'options' => $options, ...$extra]);
    }

    public function update(SaveCatalogRequest $request, string $record, CatalogDefinition $d)
    {
        $kind = $d->kind($request);
        $r = $d->record($kind, $record);
        app($d::ACTIONS[$kind])->execute($request->user(), $request->validated(), $r);

        return redirect('/'.$kind.'/'.$record)->with('success', 'Record updated.');
    }

    public function show(Request $request, string $record, CatalogDefinition $d)
    {
        $kind = $d->kind($request);
        $r = $d->record($kind, $record);
        Gate::authorize('view', $r);
        $u = $request->user();
        $scope = app(UserAccessScopeService::class);
        $related = [];
        if (in_array($kind, ['operators', 'drivers', 'vehicles'], true)) {
            $assignments = $scope->scopeAssignments($r->assignments()->getQuery(), $u)->with(['driver:id,public_id,first_name,last_name,driver_number', 'vehicle:id,public_id,registration_number', 'operator:id,public_id,name', 'park:id,public_id,name', 'route:id,public_id,origin,destination'])->latest('starts_at')->paginate(10, ['*'], 'assignments_page')->withQueryString();
            $related['assignments'] = $assignments;
            $related['current_assignments'] = $scope->scopeAssignments($r->assignments()->getQuery(), $u)->where('status', 'active')->where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->with(['driver:id,public_id,first_name,last_name,driver_number', 'vehicle:id,public_id,registration_number', 'operator:id,public_id,name', 'park:id,public_id,name', 'route:id,public_id,origin,destination'])->latest('starts_at')->paginate(10, ['*'], 'current_page')->withQueryString();
            $related['documents'] = $r->documents()->latest('id')->paginate(10, ['*'], 'documents_page')->withQueryString();
            if ($kind === 'operators') {
                // Explicit own-operator scope may view its park/route labels, without gaining access to other operators at those parks.
                $own = $u->operators()->where('operators.id', $r->id)->exists();
                $parks = $r->parks();
                if (! $own) {
                    $scope->scopeParks($parks->getQuery(), $u);
                }
                $related['parks'] = $parks->select('parks.id', 'parks.public_id', 'parks.name', 'parks.status')->paginate(10, ['*'], 'parks_page')->withQueryString();
                $routes = $r->routes();
                if (! $own && ! $scope->isStatewide($u)) {
                    $routes->whereIn('operator_route.park_id', $scope->accessibleParkIds($u));
                }
                $related['routes'] = $routes->select('routes.id', 'routes.public_id', 'routes.origin', 'routes.destination', 'routes.status')->paginate(10, ['*'], 'routes_page')->withQueryString();
            }
        }
        if ($kind === 'revenue-heads') {
            $related['fees'] = $r->fees()->latest('effective_from')->paginate(10)->withQueryString();
        }
        if ($kind === 'fee-configurations') {
            $r->load('revenueHead:id,public_id,name', 'park:id,public_id,name', 'lga:id,public_id,name', 'route:id,public_id,origin,destination');
        }

        return Inertia::render($d::PAGES[$kind].'/Show', ['kind' => $kind, 'record' => $r, 'related' => $related, 'can_update' => $u->can('update', $r), 'activities' => $u->can('view_audit_log') ? Activity::forSubject($r)->latest('id')->limit(15)->get(['description', 'created_at']) : []]);
    }

    private function options(Request $request, string $kind, bool $manage = false): array
    {
        $scope = app(UserAccessScopeService::class);
        $u = $request->user();
        $level = $manage ? AccessLevel::Manage : AccessLevel::View;
        $parks = $scope->scopeParks(Park::query(), $u, $level)->orderBy('name')->get(['id', 'public_id', 'name', 'status']);
        $options = ['parks' => $parks];
        if ($kind === 'operators' && $manage) {
            $options['operator_routes'] = Route::where('status', 'active')->whereHas('parks', fn ($p) => $p->whereIn('parks.id', $parks->pluck('id'))->where('park_route.status', 'active'))->with(['parks' => fn ($p) => $p->whereIn('parks.id', $parks->pluck('id'))->where('park_route.status', 'active')->select('parks.id', 'parks.name')])->get(['id', 'origin', 'destination'])->flatMap(fn ($r) => $r->parks->map(fn ($p) => ['id' => $p->id.':'.$r->id, 'name' => $p->name.' — '.$r->origin.' → '.$r->destination]));
        }
        if ($kind === 'fee-configurations') {
            $options['lgas'] = Lga::orderBy('name')->get(['id', 'name']);
            $options['routes'] = Route::orderBy('route_code')->get(['id', 'origin', 'destination']);
            $options['revenue_heads'] = RevenueHead::orderBy('name')->get(['id', 'name']);
        }

        return $options;
    }
}
