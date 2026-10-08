<?php

namespace App\Http\Controllers\Web;

use App\Domains\Geography\Actions\ArchiveLgaAction;
use App\Domains\Geography\Actions\SaveLgaAction;
use App\Domains\Geography\Models\Lga;
use App\Domains\Geography\Queries\LgaListQuery;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;
use App\Domains\Reporting\Queries\LocalDashboardQuery;
use App\Domains\Reporting\Queries\TransportConnectionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Http\Requests\Operations\RegistryFilterRequest;
use App\Http\Requests\Operations\SaveLgaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class LgaController extends Controller
{
    public function index(RegistryFilterRequest $request, LgaListQuery $query)
    {
        return Inertia::render('Lgas/Index', ['records' => $query->get($request->user(), $request->validated()), 'filters' => $request->validated(), 'can_create' => $request->user()->can('create', Lga::class)]);
    }

    public function create()
    {
        Gate::authorize('create', Lga::class);

        return Inertia::render('Lgas/Create');
    }

    public function store(SaveLgaRequest $request, SaveLgaAction $action)
    {
        $record = $action->execute($request->user(), $request->validated());

        return to_route('lgas.show', $record)->with('success', 'LGA created.');
    }

    public function show(Request $request, Lga $lga)
    {
        Gate::authorize('view', $lga);
        $parks = $request->user()->can('view_park') ? app(UserAccessScopeService::class)->scopeParks(Park::query(), $request->user())->where('lga_id', $lga->id)->orderBy('name')->paginate(10)->withQueryString() : null;

        return Inertia::render('Lgas/Show', ['record' => $lga, 'transport' => app(TransportConnectionsQuery::class)->get($request->user(), $lga), 'parks' => $parks, 'can_update' => $request->user()->can('update', $lga), 'can_archive' => $request->user()->can('delete', $lga), 'activities' => $request->user()->can('view_audit_log') ? Activity::forSubject($lga)->latest('id')->limit(10)->get(['description', 'created_at']) : []]);
    }

    public function edit(Lga $lga)
    {
        Gate::authorize('update', $lga);

        return Inertia::render('Lgas/Edit', ['record' => $lga]);
    }

    public function update(SaveLgaRequest $request, Lga $lga, SaveLgaAction $action)
    {
        $action->execute($request->user(), $request->validated(), $lga);

        return to_route('lgas.show', $lga)->with('success', 'LGA updated.');
    }

    public function destroy(Request $request, Lga $lga, ArchiveLgaAction $action)
    {
        $action->execute($request->user(), $lga);

        return to_route('lgas.index')->with('success', 'LGA archived.');
    }

    public function dashboard(DashboardRequest $request, Lga $lga, LocalDashboardQuery $query)
    {
        return Inertia::render('Dashboard/Lga', $query->get($request->user(), $lga, $request->validated()))->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
