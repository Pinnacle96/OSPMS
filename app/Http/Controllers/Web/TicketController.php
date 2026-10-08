<?php

namespace App\Http\Controllers\Web;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Ticketing\Actions\CancelTicketAction;
use App\Domains\Ticketing\Actions\IssueTicketAction;
use App\Domains\Ticketing\DTOs\IssueTicketData;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Ticketing\Queries\TicketDetailQuery;
use App\Domains\Ticketing\Queries\TicketListQuery;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\CancelTicketRequest;
use App\Http\Requests\Tickets\IssueTicketRequest;
use App\Http\Requests\Tickets\TicketFilterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function index(TicketFilterRequest $request, TicketListQuery $query)
    {
        $scope = app(UserAccessScopeService::class);

        return Inertia::render('Tickets/Index', [
            'records' => $query->get($request->user(), $request->validated()), 'filters' => $request->validated(),
            'can_create' => $request->user()->can('create', Ticket::class),
            'parks' => $scope->scopeParks(Park::query(), $request->user())->orderBy('name')->get(['id', 'name']),
            'lgas' => $scope->scopeLgas(Lga::query(), $request->user())->orderBy('name')->get(['id', 'name']),
            'revenue_heads' => RevenueHead::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request, TicketIssuanceService $service)
    {
        Gate::authorize('create', Ticket::class);
        $data = $request->validate(['lookup' => 'nullable|string|max:190', 'assignment_id' => 'nullable|integer|min:1', 'revenue_head_id' => 'nullable|integer|min:1']);
        $assignments = app(UserAccessScopeService::class)->scopeAssignments(DriverAssignment::query(), $request->user(), AccessLevel::Manage)
            ->where('status', 'active')->where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->whereIn('park_id', app(UserAccessScopeService::class)->scopeParks(Park::query(), $request->user(), AccessLevel::Manage)->select('parks.id'));
        if (! empty($data['lookup'])) {
            $term = '%'.$data['lookup'].'%';
            $assignments->where(fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('registration_number', 'like', $term))
                ->orWhereHas('driver', fn ($d) => $d->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('driver_number', 'like', $term))
                ->orWhereHas('operator', fn ($o) => $o->where('name', 'like', $term)->orWhere('operator_number', 'like', $term)));
        }
        $choices = (clone $assignments)->with(['driver:id,first_name,last_name,driver_number', 'vehicle:id,registration_number', 'operator:id,name', 'park:id,name', 'route:id,origin,destination'])->latest('starts_at')->limit(50)->get()->map(fn ($a) => [
            'id' => $a->id, 'label' => $a->vehicle?->registration_number.' · '.$a->driver?->first_name.' '.$a->driver?->last_name.' · '.$a->operator?->name.' · '.$a->park?->name,
        ]);
        $review = null;
        if (! empty($data['assignment_id']) && ! empty($data['revenue_head_id'])) {
            $resolved = $service->review($request->user(), (int) $data['assignment_id'], (int) $data['revenue_head_id']);
            $review = [...$resolved['terms'], 'confirmation' => $resolved['confirmation']];
            if (! $choices->contains('id', (int) $data['assignment_id'])) {
                $context = $review['context_snapshot'];
                $choices->push(['id' => (int) $data['assignment_id'], 'label' => $context['vehicle']['registration'].' · '.$context['driver']['name'].' · '.$context['operator']['name'].' · '.$context['park']['name']]);
            }
        }

        return Inertia::render('Tickets/Create', ['assignments' => $choices, 'revenue_heads' => RevenueHead::where('status', 'active')->orderBy('name')->get(['id', 'name']), 'filters' => $data, 'review' => $review]);
    }

    public function store(IssueTicketRequest $request, IssueTicketAction $action)
    {
        $ticket = $action->execute($request->user(), IssueTicketData::fromArray($request->validated()));

        return to_route('tickets.show', $ticket)->with('success', 'Ticket issued. Payment status is unpaid.');
    }

    public function show(Request $request, Ticket $ticket, TicketDetailQuery $query)
    {
        Gate::authorize('view', $ticket);

        return Inertia::render('Tickets/Show', $query->get($request->user(), $ticket))->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function print(Request $request, Ticket $ticket, TicketDetailQuery $query)
    {
        Gate::authorize('view', $ticket);
        activity('ticketing')->causedBy($request->user())->performedOn($ticket)->log('ticket_print_viewed');

        return Inertia::render('Tickets/Print', $query->get($request->user(), $ticket))->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function cancel(Request $request, Ticket $ticket, TicketDetailQuery $query)
    {
        Gate::authorize('cancel', $ticket);

        return Inertia::render('Tickets/Cancel', $query->get($request->user(), $ticket));
    }

    public function close(CancelTicketRequest $request, Ticket $ticket, CancelTicketAction $action)
    {
        $action->execute($request->user(), $ticket, $request->validated('reason'));

        return to_route('tickets.show', $ticket)->with('success', 'Ticket cancelled. Its original terms and audit history are retained.');
    }
}
