<?php

namespace App\Http\Controllers\Web;

use App\Domains\Enforcement\Actions\RecordViolationAction;
use App\Domains\Enforcement\Actions\ResolveViolationAction;
use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Enforcement\Models\Violation;
use App\Domains\Incidents\Services\OperationalRecordView;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enforcement\RecordViolationRequest;
use App\Http\Requests\Enforcement\ResolveViolationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ViolationController extends Controller
{
    public function index(Request $r)
    {
        return Inertia::render('Violations/Index', app(OperationalRecordView::class)->listing($r, Violation::class));
    }

    public function store(RecordViolationRequest $r, Inspection $inspection)
    {
        $v = app(RecordViolationAction::class)->execute($r->user(), $inspection, $r->validated());

        return redirect('/violations/'.$v->public_id, 303)->with('success', 'Violation observation recorded.');
    }

    public function show(Request $r, Violation $violation)
    {
        Gate::authorize('view', $violation);

        return Inertia::render('Violations/Show', ['record' => app(OperationalRecordView::class)->record($violation, $r->user(), true), 'can_resolve' => Gate::allows('resolve', $violation) && $violation->status->value === 'open', 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function resolve(ResolveViolationRequest $r, Violation $violation)
    {
        app(ResolveViolationAction::class)->execute($r->user(), $violation, $r->validated());

        return redirect('/violations/'.$violation->public_id, 303)->with('success', 'Violation resolution recorded.');
    }
}
