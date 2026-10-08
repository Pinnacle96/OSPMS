<?php

namespace App\Http\Controllers\Web;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Actions\ArchiveParkAction;
use App\Domains\Parks\Actions\AssignParkRoutesAction;
use App\Domains\Parks\Actions\SaveParkAction;
use App\Domains\Parks\Models\Park;
use App\Domains\Parks\Queries\ParkListQuery;
use App\Domains\Reporting\Queries\LocalDashboardQuery;
use App\Domains\Reporting\Queries\TransportConnectionsQuery;
use App\Domains\Routes\Models\Route;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\AssignParkRoutesRequest;
use App\Http\Requests\Operations\RegistryFilterRequest;
use App\Http\Requests\Operations\SaveParkRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class ParkController extends Controller
{
    public function index(RegistryFilterRequest $request, ParkListQuery $query)
    {
        $scopes = app(UserAccessScopeService::class);
        $lgas = Lga::whereIn('id', $scopes->scopeParks(Park::query(), $request->user())->select('lga_id'))->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Parks/Index', ['records' => $query->get($request->user(), $request->validated()), 'filters' => $request->validated(), 'lgas' => $lgas, 'can_create' => $request->user()->can('create', Park::class)]);
    }

    public function create()
    {
        Gate::authorize('create', Park::class);

        return Inertia::render('Parks/Create', ['lgas' => Lga::orderBy('name')->get(['id', 'name', 'status'])]);
    }

    public function store(SaveParkRequest $request, SaveParkAction $action)
    {
        $record = $action->execute($request->user(), $request->validated());

        return to_route('parks.show', $record)->with('success', 'Park registered.');
    }

    public function show(Request $request, Park $park)
    {
        Gate::authorize('view', $park);
        $canAssign = $request->user()->can('assignRoutes', $park);
        $park->load('lga:id,public_id,name');
        $routes = $park->routes()->orderBy('route_code')->paginate(10)->withQueryString();

        return Inertia::render('Parks/Show', ['record' => $park, 'transport' => app(TransportConnectionsQuery::class)->get($request->user(), $park), 'can_view_lga' => $request->user()->can('view', $park->lga), 'managers' => User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('name', 'Park Manager'))->whereHas('parks', fn ($q) => $q->where('parks.id', $park->id))->get(['name']), 'assigned_routes' => $routes, 'route_options' => $canAssign ? Route::where('status', 'active')->orderBy('route_code')->get(['id', 'route_code', 'origin', 'destination']) : [],
            'selected_route_ids' => $canAssign ? $park->routes()->where('routes.status', 'active')->wherePivot('status', 'active')->pluck('routes.id') : [],
            'can_update' => $request->user()->can('update', $park), 'can_archive' => $request->user()->can('delete', $park), 'can_assign_routes' => $canAssign,
            'activities' => $request->user()->can('view_audit_log') ? Activity::forSubject($park)->latest('id')->limit(10)->get(['description', 'created_at']) : []]);
    }

    public function edit(Request $request, Park $park)
    {
        Gate::authorize('update', $park);
        $q = app(UserAccessScopeService::class)->scopeLgas(Lga::query(), $request->user(), AccessLevel::Manage);
        $q->orWhere('id', $park->lga_id);

        return Inertia::render('Parks/Edit', ['record' => $park, 'lgas' => $q->orderBy('name')->get(['id', 'name', 'status'])]);
    }

    public function update(SaveParkRequest $request, Park $park, SaveParkAction $action)
    {
        $action->execute($request->user(), $request->validated(), $park);

        return to_route('parks.show', $park)->with('success', 'Park updated.');
    }

    public function destroy(Request $request, Park $park, ArchiveParkAction $action)
    {
        $action->execute($request->user(), $park);

        return to_route('parks.index')->with('success', 'Park archived.');
    }

    public function routes(AssignParkRoutesRequest $request, Park $park, AssignParkRoutesAction $action)
    {
        $action->execute($request->user(), $park, $request->validated('route_ids'));

        return back()->with('success', 'Approved routes updated.');
    }

    public function dashboard(Request $request, Park $park, LocalDashboardQuery $query)
    {
        return Inertia::render('Dashboard/Park', $query->get($request->user(), $park));
    }
}
