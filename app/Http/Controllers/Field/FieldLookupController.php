<?php

namespace App\Http\Controllers\Field;

use App\Domains\Enforcement\Services\ComplianceSummaryService;
use App\Domains\Enforcement\Services\FieldLookupService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;

abstract class FieldLookupController extends Controller
{
    abstract protected function kind(): string;

    public function index(Request $request, FieldLookupService $service)
    {
        $filters = $request->validate(['search' => 'nullable|string|min:2|max:100']);
        $kind = $this->kind();

        return Inertia::render('Field/'.ucfirst($kind).'/Search', ['records' => $service->search($request->user(), $kind, $filters), 'filters' => $filters]);
    }

    protected function detail(Request $request, Model $record)
    {
        $kind = $this->kind();
        $s = app(FieldLookupService::class);
        $s->authorize($request->user(), $record, $kind);
        $foreign = match ($kind) {
            'drivers' => 'driver_id','vehicles' => 'vehicle_id',default => 'operator_id'
        };

        return Inertia::render('Field/'.ucfirst($kind).'/Show', ['record' => $s->safe($record, $kind), 'assignments' => $s->related($request->user(), $record, $kind), 'compliance' => app(ComplianceSummaryService::class)->get($record, $kind, $s->current($request->user())->where($foreign, $record->id)->exists()), 'inspection_url' => $request->user()->can('record_inspection') ? '/field/inspections/create?subject='.$kind.'&record='.$record->public_id : null]);
    }
}
