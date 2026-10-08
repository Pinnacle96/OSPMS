<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Drivers\Models\Driver;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Enums\ParkStatus;
use App\Domains\Parks\Models\Park;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class StateDashboardQuery
{
    public function get(User $user, array $range): array
    {
        Gate::forUser($user)->authorize('view_state_dashboard');
        $scopes = app(UserAccessScopeService::class);
        $statewide = $scopes->isStatewide($user);
        $metrics = [
            ['label' => "Today's revenue", 'value' => '0.00', 'money' => true, 'icon' => 'wallet', 'note' => 'Collections not yet enabled'],
            ['label' => "Today's transactions", 'value' => 0, 'icon' => 'receipt', 'note' => 'No financial records'],
            ['label' => 'Active parks', 'value' => $scopes->scopeParks(Park::query(), $user)->where('status', ParkStatus::Active->value)->count(), 'icon' => 'park', 'note' => 'Registered operational parks'],
            ['label' => 'Registered operators', 'value' => $scopes->scopeOperators(Operator::query(), $user)->count(), 'icon' => 'users', 'note' => 'Within your data scope'],
            ['label' => 'Registered vehicles', 'value' => $scopes->scopeParticipants(Vehicle::query(), $user)->count(), 'icon' => 'vehicle', 'note' => 'Within your data scope'],
            ['label' => 'Registered drivers', 'value' => $scopes->scopeParticipants(Driver::query(), $user)->count(), 'icon' => 'driver', 'note' => 'Within your data scope'],
            ['label' => 'Successful payments', 'value' => 0, 'icon' => 'check', 'note' => 'No payment records'],
            ['label' => 'Failed payments', 'value' => 0, 'icon' => 'alert', 'note' => 'No payment records'],
            ['label' => 'Pending reconciliation', 'value' => 0, 'icon' => 'reconcile', 'note' => 'No items awaiting review'],
        ];
        $activity = Activity::query()->whereBetween('created_at', [$range['start_utc'], $range['end_utc']]);
        // General audit visibility is permission-controlled; other viewers receive only their own activity.
        if (! $user->can('view_audit_log')) {
            $activity->where('causer_type', User::class)->where('causer_id', $user->id);
        }

        return ['metrics' => $metrics, 'scope_label' => $statewide ? 'Statewide' : 'Assigned access scopes',
            'lga_count' => $scopes->scopeLgas(Lga::query(), $user)->count(),
            'activities' => $activity->latest('id')->limit(6)->get(['description', 'created_at']),
            'revenue_trend' => [], 'revenue_by_lga' => [], 'revenue_by_park' => [], 'payment_status' => [], 'exceptions' => [],
        ];
    }
}
