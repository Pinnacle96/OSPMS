<?php

namespace App\Http\Controllers\Web;

use App\Domains\Reporting\Queries\StateDashboardQuery;
use App\Domains\Reporting\Services\DashboardDateRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, StateDashboardQuery $query)
    {
        $range = app(DashboardDateRange::class)->resolve($request->validated());
        $filters = [...$request->validated(), 'from' => $range['from'], 'to' => $range['to']];
        $mode = $request->routeIs('dashboard.executive') ? 'executive' : ($request->routeIs('dashboard.revenue') ? 'revenue' : 'state');
        $component = match ($mode) {
            'executive' => 'Executive', 'revenue' => 'Revenue', default => 'State'
        };

        return Inertia::render('Dashboard/'.$component, [...$query->get($request->user(), $range, $filters, $mode), 'filters' => $filters, 'as_of' => now()->toIso8601String()])->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
