<?php

namespace App\Http\Controllers\Web;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;
use App\Domains\Reporting\Queries\TransportConnectionsQuery;
use App\Domains\Routes\Actions\ArchiveRouteAction;
use App\Domains\Routes\Actions\SaveRouteAction;
use App\Domains\Routes\Models\Route;
use App\Domains\Routes\Queries\RouteListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\RegistryFilterRequest;
use App\Http\Requests\Operations\SaveRouteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class RouteController extends Controller
{
    public function index(RegistryFilterRequest $request, RouteListQuery $query)
    {
        $scopes = app(UserAccessScopeService::class);

        return Inertia::render('Routes/Index', ['records' => $query->get($request->user(), $request->validated()), 'filters' => $request->validated(), 'lgas' => Lga::whereIn('id', $scopes->scopeParks(Park::query(), $request->user())->select('lga_id'))->orderBy('name')->get(['id', 'name']), 'can_create' => $request->user()->can('create', Route::class)]);
    }

    public function create()
    {
        Gate::authorize('create', Route::class);

        return Inertia::render('Routes/Create');
    }

    public function store(SaveRouteRequest $request, SaveRouteAction $action)
    {
        $record = $action->execute($request->user(), $request->validated());

        return to_route('routes.show', $record)->with('success', 'Route created.');
    }

    public function show(Request $request, Route $route)
    {
        Gate::authorize('view', $route);
        $scopes = app(UserAccessScopeService::class);
        $parks = $route->parks()->wherePivot('status', 'active');
        $scopes->scopeParks($parks->getQuery(), $request->user());

        return Inertia::render('Routes/Show', ['record' => $route, 'transport' => app(TransportConnectionsQuery::class)->get($request->user(), $route), 'parks' => $request->user()->can('view_park') ? $parks->with('lga:id,name')->orderBy('name')->paginate(10)->withQueryString() : null, 'can_update' => $request->user()->can('update', $route), 'can_archive' => $request->user()->can('delete', $route), 'activities' => $request->user()->can('view_audit_log') ? Activity::forSubject($route)->latest('id')->limit(10)->get(['description', 'created_at']) : []]);
    }

    public function edit(Route $route)
    {
        Gate::authorize('update', $route);

        return Inertia::render('Routes/Edit', ['record' => $route]);
    }

    public function update(SaveRouteRequest $request, Route $route, SaveRouteAction $action)
    {
        $action->execute($request->user(), $request->validated(), $route);

        return to_route('routes.show', $route)->with('success', 'Route updated.');
    }

    public function destroy(Request $request, Route $route, ArchiveRouteAction $action)
    {
        $action->execute($request->user(), $route);

        return to_route('routes.index')->with('success', 'Route archived.');
    }
}
