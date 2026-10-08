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
    public function get(User $user, array $range, array $filters = [], string $mode = 'state'): array
    {
        Gate::forUser($user)->authorize(match ($mode) {
            'executive' => 'view_executive_dashboard', 'revenue' => 'view_revenue_dashboard', default => 'view_state_dashboard',
        });
        $scopes = app(UserAccessScopeService::class);
        $statewide = $scopes->isStatewide($user);
        $finance = app(RevenueDashboardQuery::class)->get($user, $filters, $range);
        $metrics = [
            ['label' => "Today's revenue", 'value' => $finance['summary']['today']['net'] ?? null, 'money' => true, 'icon' => 'wallet', 'note' => $finance ? 'Ledger credits minus debits • '.config('ospm.timezone').' today' : 'Revenue access restricted'],
            ['label' => "Today's transactions", 'value' => $finance['summary']['today']['transactions'] ?? null, 'icon' => 'receipt', 'note' => $finance ? 'Credits and debits • '.config('ospm.timezone').' today' : 'Revenue access restricted'],
            ['label' => 'Active parks', 'value' => $scopes->scopeParks(Park::query(), $user)->where('status', ParkStatus::Active->value)->count(), 'icon' => 'park', 'note' => 'Registered operational parks'],
            ['label' => 'Registered operators', 'value' => $scopes->scopeOperators(Operator::query(), $user)->count(), 'icon' => 'users', 'note' => 'Within your data scope'],
            ['label' => 'Registered vehicles', 'value' => $scopes->scopeParticipants(Vehicle::query(), $user)->count(), 'icon' => 'vehicle', 'note' => 'Within your data scope'],
            ['label' => 'Registered drivers', 'value' => $scopes->scopeParticipants(Driver::query(), $user)->count(), 'icon' => 'driver', 'note' => 'Within your data scope'],
            ['label' => 'Successful payments', 'value' => $finance['payment_status'][0]['count'] ?? null, 'icon' => 'check', 'note' => 'Current status • attempts in selected period'],
            ['label' => 'Failed payments', 'value' => $finance['payment_status'][1]['count'] ?? null, 'icon' => 'alert', 'note' => 'Current status • attempts in selected period'],
            ['label' => 'Pending reconciliation', 'value' => null, 'icon' => 'reconcile', 'note' => 'Available in Milestone 10'],
        ];
        $activity = Activity::query()->where('created_at', '>=', $range['start_utc'])->where('created_at', '<', $range['end_utc']);
        // General audit visibility is permission-controlled; other viewers receive only their own activity.
        if (! $statewide || ! $user->can('view_audit_log')) {
            $activity->where('causer_type', User::class)->where('causer_id', $user->id);
        }

        return ['metrics' => $metrics, 'scope_label' => $statewide ? 'Statewide' : 'Assigned access scopes',
            'lga_count' => $scopes->scopeLgas(Lga::query(), $user)->count(),
            'activities' => $activity->latest('id')->limit(6)->get(['description', 'created_at']),
            'finance' => $finance,
        ];
    }
}
