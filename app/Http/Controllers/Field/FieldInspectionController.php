<?php

namespace App\Http\Controllers\Field;

use App\Domains\Enforcement\Actions\RecordInspectionAction;
use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Enforcement\Services\FieldLookupService;
use App\Domains\Enforcement\Services\InspectionContextService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enforcement\RecordInspectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FieldInspectionController extends Controller
{
    public function create(Request $request)
    {
        Gate::authorize('create', Inspection::class);
        $data = $request->validate(['ticket' => 'nullable|string|size:26', 'subject' => 'nullable|in:drivers,vehicles,operators', 'record' => 'nullable|string|size:26']);
        $ticket = null;
        $contexts = [];
        if ($data['ticket'] ?? null) {
            $context = app(InspectionContextService::class)->resolve($request->user(), $data);
            $ticket = ['public_id' => $data['ticket'], 'reference' => $context['reference'], 'valid' => $context['ticket_valid']];
        } else {
            $contexts = app(FieldLookupService::class)->contexts($request->user(), $data['subject'] ?? null, $data['record'] ?? null);
        }

        return Inertia::render('Field/Inspections/Create', ['ticket' => $ticket, 'contexts' => $contexts, 'default_type' => $ticket ? 'ticket_verification' : match ($data['subject'] ?? 'vehicles') {
            'drivers' => 'driver_check','operators' => 'operator_check',default => 'vehicle_check'
        }, 'idempotency_key' => bin2hex(random_bytes(32))]);
    }

    public function store(RecordInspectionRequest $request, RecordInspectionAction $action)
    {
        $r = $action->execute($request->user(), $request->validated());

        return redirect('/field/')->setTargetUrl('/field/')->with('success', 'Inspection '.$r->inspection_reference.' recorded.');
    }
}
