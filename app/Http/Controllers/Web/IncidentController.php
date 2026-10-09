<?php

namespace App\Http\Controllers\Web;

use App\Domains\Enforcement\Services\FieldLookupService;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Incidents\Actions\CreateIncidentAction;
use App\Domains\Incidents\Actions\ManageIncidentAction;
use App\Domains\Incidents\Models\Incident;
use App\Domains\Incidents\Services\OperationalRecordView;
use App\Domains\Parks\Models\Park;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\CreateIncidentRequest;
use App\Http\Requests\Incidents\ManageIncidentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class IncidentController extends Controller
{
    public function index(Request $r)
    {
        return Inertia::render('Incidents/Index', [...app(OperationalRecordView::class)->listing($r, Incident::class), 'can_create' => $r->user()->can('create_incident')]);
    }

    public function create(Request $r)
    {
        Gate::authorize('create', Incident::class);
        $field = $r->routeIs('field.incidents.create');

        return Inertia::render($field ? 'Field/Incidents/Create' : 'Incidents/Create', ['parks' => app(UserAccessScopeService::class)->scopeParks(Park::query(), $r->user())->where('status', 'active')->orderBy('name')->get(['public_id', 'name']), 'contexts' => app(FieldLookupService::class)->contexts($r->user()), 'categories' => CreateIncidentRequest::CATEGORIES, 'occurred_at' => now()->setTimezone('Africa/Lagos')->format('Y-m-d\TH:i'), 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function store(CreateIncidentRequest $r)
    {
        $i = app(CreateIncidentAction::class)->execute($r->user(), $r->validated());

        return redirect('/incidents/'.$i->public_id.($r->routeIs('field.incidents.store') ? '?field=1' : ''), 303)->with('success', 'Incident reported.');
    }

    public function show(Request $r, Incident $incident)
    {
        Gate::authorize('view', $incident);

        return Inertia::render('Incidents/Show', ['record' => app(OperationalRecordView::class)->record($incident, $r->user(), true), 'can_manage' => Gate::allows('manage', $incident), 'field' => $r->boolean('field') && $r->user()->can('access_field'), 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function manage(Request $r, Incident $incident)
    {
        Gate::authorize('manage', $incident);

        return Inertia::render('Incidents/Manage', ['record' => app(OperationalRecordView::class)->record($incident, $r->user(), true), 'transitions' => ManageIncidentAction::TRANSITIONS[$incident->status->value], 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function update(ManageIncidentRequest $r, Incident $incident)
    {
        app(ManageIncidentAction::class)->execute($r->user(), $incident, $r->validated());

        return redirect('/incidents/'.$incident->public_id, 303)->with('success', 'Incident status recorded.');
    }
}
