<?php

namespace App\Http\Controllers\Web;

use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Incidents\Services\OperationalRecordView;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InspectionController extends Controller
{
    public function index(Request $r)
    {
        return Inertia::render('Inspections/Index', app(OperationalRecordView::class)->listing($r, Inspection::class));
    }

    public function show(Request $r, Inspection $inspection)
    {
        Gate::authorize('view', $inspection);

        return Inertia::render('Inspections/Show', ['record' => app(OperationalRecordView::class)->record($inspection, $r->user(), true), 'can_record_violation' => $r->user()->can('record_violation') && $inspection->result->value !== 'compliant', 'idempotency_key' => bin2hex(random_bytes(32))]);
    }
}
