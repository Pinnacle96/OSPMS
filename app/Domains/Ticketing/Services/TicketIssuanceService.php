<?php

namespace App\Domains\Ticketing\Services;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Revenue\Services\ResolveApplicableFeeService;
use App\Domains\Routes\Models\Route;
use App\Domains\Ticketing\Models\Ticket;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TicketIssuanceService
{
    public function review(User $user, int $assignmentId, int $headId, bool $lock = false, ?string $requestKey = null): array
    {
        Gate::forUser($user)->authorize('create', Ticket::class);
        $requestKey ??= bin2hex(random_bytes(16));
        if (! preg_match('/\A[a-f0-9]{32}\z/D', $requestKey)) {
            throw ValidationException::withMessages(['confirmation' => 'Review the ticket again before issuing.']);
        }
        $scope = app(UserAccessScopeService::class);
        $assignment = DriverAssignment::findOrFail($assignmentId);
        abort_unless($scope->scopeAssignments(DriverAssignment::query(), $user, AccessLevel::Manage)->whereKey($assignmentId)->exists(), 403);
        // Follow assignment creation's lock order; ending locks the assignment.
        $load = function (string $model, int $id) use ($lock) {
            $query = $model::whereKey($id);

            return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
        };
        $driver = $load(Driver::class, $assignment->driver_id);
        $vehicle = $load(Vehicle::class, $assignment->vehicle_id);
        $operator = $load(Operator::class, $assignment->operator_id);
        $park = $load(Park::class, $assignment->park_id);
        $lga = $load(Lga::class, $park->lga_id);
        $route = $assignment->route_id ? $load(Route::class, $assignment->route_id) : null;
        if ($lock) {
            $fresh = DriverAssignment::whereKey($assignmentId)->lockForUpdate()->firstOrFail();
            if ($fresh->only(['driver_id', 'vehicle_id', 'operator_id', 'park_id', 'route_id']) !== $assignment->only(['driver_id', 'vehicle_id', 'operator_id', 'park_id', 'route_id'])) {
                throw ValidationException::withMessages(['assignment_id' => 'The assignment changed. Select it again.']);
            }
            $assignment = $fresh;
        }
        abort_unless($scope->canAccessPark($user, $park, AccessLevel::Manage), 403);
        if ($assignment->status->value !== 'active' || $assignment->starts_at->gt(now()) || ($assignment->ends_at && $assignment->ends_at->lte(now()))) {
            throw ValidationException::withMessages(['assignment_id' => 'Select a current active assignment.']);
        }
        foreach ([$driver, $vehicle, $park, $lga] as $participant) {
            if ($participant->status->value !== 'active') {
                throw ValidationException::withMessages(['assignment_id' => 'The driver, vehicle, park and LGA must all be active.']);
            }
        }
        $approved = function ($query) use ($lock): bool {
            return ($lock ? $query->lockForUpdate() : $query)->first() !== null;
        };
        if ($operator->status->value !== 'approved' || ! $approved($operator->parks()->where('parks.id', $park->id)->wherePivot('status', 'active'))) {
            throw ValidationException::withMessages(['assignment_id' => 'The operator must be approved for this park.']);
        }
        if ($route && ($route->status->value !== 'active' || ! $approved($park->routes()->where('routes.id', $route->id)->wherePivot('status', 'active')) || ! $approved($operator->routes()->where('routes.id', $route->id)->wherePivot('park_id', $park->id)->wherePivot('status', 'active')))) {
            throw ValidationException::withMessages(['assignment_id' => 'The route must be active and approved for this park and operator.']);
        }
        $head = $load(RevenueHead::class, $headId);
        $fee = app(ResolveApplicableFeeService::class)->resolve($head, $vehicle->vehicle_type, $park, $route?->id, lock: $lock);
        $context = [
            'issuance_request_key' => $requestKey,
            'assignment_id' => $assignment->id,
            'lga' => ['name' => $lga->name, 'code' => $lga->code],
            'park' => ['name' => $park->name, 'code' => $park->park_code],
            'operator' => ['name' => $operator->name, 'reference' => $operator->operator_number],
            'driver' => ['name' => trim($driver->first_name.' '.$driver->last_name), 'reference' => $driver->driver_number],
            'vehicle' => ['registration' => $vehicle->registration_number, 'type' => $vehicle->vehicle_type],
            'route' => $route ? ['origin' => $route->origin, 'destination' => $route->destination] : null,
        ];
        $minutes = config('ospm.ticket_expiry_minutes');
        $terms = ['assignment_id' => $assignmentId, 'revenue_head_id' => $headId, 'fee_configuration_id' => $fee->id, 'fee_code_snapshot' => $head->code, 'fee_name_snapshot' => $head->name, 'amount' => $fee->amount, 'currency' => $fee->currency, 'context_snapshot' => $context, 'expiry_minutes' => $minutes];
        $terms['request_key'] = $requestKey;
        $confirmation = hash_hmac('sha256', $user->id.'|'.json_encode($terms, JSON_THROW_ON_ERROR), config('app.key'));

        return ['terms' => $terms, 'confirmation' => $confirmation, 'attributes' => [
            'revenue_head_id' => $head->id, 'fee_configuration_id' => $fee->id, 'lga_id' => $lga->id, 'park_id' => $park->id,
            'operator_id' => $operator->id, 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'route_id' => $route?->id,
            'fee_code_snapshot' => $head->code, 'fee_name_snapshot' => $head->name, 'amount' => $fee->amount, 'currency' => $fee->currency, 'context_snapshot' => $context,
            'expires_at' => $minutes ? now()->addMinutes($minutes) : null,
        ]];
    }
}
