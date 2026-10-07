<?php

namespace App\Http\Controllers\Web;

use App\Domains\Reporting\Queries\StateDashboardQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, StateDashboardQuery $query)
    {
        $timezone = config('ospm.timezone');
        $today = CarbonImmutable::now($timezone)->format('Y-m-d');
        $filters = ['from' => $request->validated('from') ?: $today, 'to' => $request->validated('to') ?: $today];
        if ($filters['to'] < $filters['from']) {
            throw ValidationException::withMessages(['to' => 'The end date must follow the start date.']);
        }
        $range = ['start_utc' => CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()->utc(), 'end_utc' => CarbonImmutable::parse($filters['to'], $timezone)->endOfDay()->utc()];

        return Inertia::render('Dashboard/State', [...$query->get($request->user(), $range), 'filters' => $filters, 'as_of' => now()->toIso8601String()]);
    }
}
