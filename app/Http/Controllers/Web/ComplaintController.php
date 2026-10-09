<?php

namespace App\Http\Controllers\Web;

use App\Domains\Complaints\Actions\CreateComplaintAction;
use App\Domains\Complaints\Actions\ManageComplaintAction;
use App\Domains\Complaints\Enums\ComplaintStatus;
use App\Domains\Complaints\Models\Complaint;
use App\Domains\Complaints\Services\ComplaintScope;
use App\Domains\Complaints\Services\ComplaintView;
use App\Domains\Enforcement\Services\FieldLookupService;
use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Services\OperationalScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Complaints\CreateComplaintRequest;
use App\Http\Requests\Complaints\ManageComplaintRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ComplaintController extends Controller
{
    public function index(Request $r)
    {
        Gate::authorize('viewAny', Complaint::class);
        $f = $r->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:submitted,received,assigned,under_review,resolved,closed', 'assigned' => 'nullable|in:me,unassigned']);
        $q = app(ComplaintScope::class)->query(Complaint::query(), $r->user())->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('complaint_reference', 'like', '%'.$s.'%')->orWhere('category', 'like', '%'.$s.'%')))->when(($f['assigned'] ?? null) === 'me', fn ($q) => $q->where('assigned_to', $r->user()->id))->when(($f['assigned'] ?? null) === 'unassigned', fn ($q) => $q->whereNull('assigned_to'));
        $rows = $q->with(['park', 'assignee'])->orderByDesc('id')->paginate(15)->withQueryString()->through(fn ($c) => app(ComplaintView::class)->record($c, $r->user()));

        return Inertia::render('Complaints/Index', ['records' => $rows, 'filters' => $f, 'statuses' => array_column(ComplaintStatus::cases(), 'value'), 'can_create' => Gate::allows('create', Complaint::class)]);
    }

    public function create(Request $r)
    {
        Gate::authorize('create', Complaint::class);

        return Inertia::render('Complaints/Create', ['parks' => app(OperationalScope::class)->parks($r->user())->whereNull('deleted_at')->where('status', 'active')->orderBy('name')->get(['public_id', 'name']), 'contexts' => app(FieldLookupService::class)->contexts($r->user()), 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function store(CreateComplaintRequest $r)
    {
        $c = app(CreateComplaintAction::class)->execute($r->user(), $r->validated());

        return redirect('/complaints/'.$c->public_id, 303)->with('success', 'Complaint recorded.');
    }

    public function show(Request $r, Complaint $complaint)
    {
        Gate::authorize('view', $complaint);
        $manage = Gate::allows('manage', $complaint);
        $assignees = $manage && ! in_array($complaint->status->value, ['submitted', 'resolved', 'closed']) ? User::where('status', 'active')->permission('manage_complaint')->orderBy('name')->get()->filter(fn ($u) => app(ComplaintScope::class)->canAssign($u, $complaint))->map(fn ($u) => $u->only('public_id', 'name'))->values() : [];

        return Inertia::render('Complaints/Show', ['record' => app(ComplaintView::class)->record($complaint, $r->user(), true), 'can_manage' => $manage, 'assignees' => $assignees, 'transitions' => ManageComplaintAction::TRANSITIONS[$complaint->status->value], 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function update(ManageComplaintRequest $r, Complaint $complaint)
    {
        app(ManageComplaintAction::class)->execute($r->user(), $complaint, $r->validated());

        return back(303)->with('success', 'Complaint history updated.');
    }
}
