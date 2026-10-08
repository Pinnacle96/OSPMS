<?php

namespace App\Http\Controllers\Web;

use App\Domains\Assignments\Actions\CreateAssignmentAction;
use App\Domains\Assignments\Actions\EndAssignmentAction;
use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Routes\Models\Route;
use App\Domains\Vehicles\Models\Vehicle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\CatalogFilterRequest;
use App\Http\Requests\Operations\SaveAssignmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    private const RELATIONS = ['driver:id,public_id,first_name,last_name,driver_number', 'vehicle:id,public_id,registration_number', 'operator:id,public_id,name', 'park:id,public_id,name', 'route:id,public_id,origin,destination'];

    public function index(CatalogFilterRequest $request)
    {
        Gate::authorize('viewAny', DriverAssignment::class);
        $s = app(UserAccessScopeService::class);
        $f = $request->validated();
        $q = $s->scopeAssignments(DriverAssignment::query(), $request->user());
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }
        foreach (['park_id', 'operator_id'] as $key) {
            if (! empty($f[$key])) {
                $q->where($key, $f[$key]);
            }
        }
        if (! empty($f['search'])) {
            $q->where(function ($q) use ($f) {
                $term = '%'.$f['search'].'%';
                $q->whereHas('driver', fn ($d) => $d->where(fn ($d) => $d->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('driver_number', 'like', $term)))->orWhereHas('vehicle', fn ($v) => $v->where('registration_number', 'like', $term))->orWhereHas('operator', fn ($o) => $o->where('name', 'like', $term));
            });
        }

        return Inertia::render('Assignments/Index', ['records' => $q->with(self::RELATIONS)->orderBy(in_array($f['sort'] ?? '', ['starts_at', 'status'], true) ? $f['sort'] : 'starts_at', $f['direction'] ?? 'desc')->orderBy('id', 'desc')->paginate(15)->withQueryString(), 'filters' => $f, 'can_create' => $request->user()->can('create', DriverAssignment::class)]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', DriverAssignment::class);
        $request->validate(['lookup' => 'nullable|string|max:190']);
        $u = $request->user();
        $s = app(UserAccessScopeService::class);
        $level = AccessLevel::Manage;
        $drivers = $s->scopeParticipants(Driver::where('status', 'active'), $u, $level);
        $vehicles = $s->scopeParticipants(Vehicle::where('status', 'active'), $u, $level);
        $operators = $s->scopeOperators(Operator::where('status', 'approved'), $u, $level);
        $routes = $s->scopeRoutes(Route::where('status', 'active'), $u);
        if ($term = $request->input('lookup')) {
            $operators->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('operator_number', 'like', '%'.$term.'%'));
            $routes->where(fn ($q) => $q->where('origin', 'like', '%'.$term.'%')->orWhere('destination', 'like', '%'.$term.'%')->orWhere('route_code', 'like', '%'.$term.'%'));
            $drivers->where(fn ($q) => $q->where('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')->orWhere('driver_number', 'like', '%'.$term.'%'));
            $vehicles->where(fn ($q) => $q->where('registration_number', 'like', '%'.$term.'%')->orWhere('vehicle_number', 'like', '%'.$term.'%'));
        }

        return Inertia::render('Assignments/Create', ['options' => [
            'drivers' => $drivers->orderBy('first_name')->limit(100)->get(['id', 'first_name', 'last_name', 'driver_number']),
            'vehicles' => $vehicles->orderBy('registration_number')->limit(100)->get(['id', 'registration_number']),
            'operators' => $operators->orderBy('name')->limit(100)->get(['id', 'name']),
            'parks' => $s->scopeParks(Park::where('status', 'active'), $u, $level)->orderBy('name')->get(['id', 'name']),
            'routes' => $routes->orderBy('route_code')->limit(100)->get(['id', 'origin', 'destination']),
        ], 'lookup' => $request->input('lookup', '')]);
    }

    public function store(SaveAssignmentRequest $request, CreateAssignmentAction $action)
    {
        $r = $action->execute($request->user(), $request->validated());

        return to_route('assignments.show', $r)->with('success', 'Assignment created.');
    }

    public function show(Request $request, DriverAssignment $assignment)
    {
        Gate::authorize('view', $assignment);

        return Inertia::render('Assignments/Show', ['record' => $assignment->load(self::RELATIONS), 'can_end' => $request->user()->can('end', $assignment) && $assignment->status->value !== 'ended']);
    }

    public function end(DriverAssignment $assignment)
    {
        Gate::authorize('end', $assignment);

        return Inertia::render('Assignments/End', ['record' => $assignment->load(self::RELATIONS)]);
    }

    public function close(Request $request, DriverAssignment $assignment, EndAssignmentAction $action)
    {
        Gate::authorize('end', $assignment);
        $data = $request->validate(['ends_at' => 'required|date|before_or_equal:now']);
        $action->execute($request->user(), $assignment, $data['ends_at']);

        return to_route('assignments.show', $assignment)->with('success', 'Assignment ended. History retained.');
    }
}
