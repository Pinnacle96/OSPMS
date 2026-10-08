<?php

namespace App\Domains\Assignments\Services;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Domains\Routes\Models\Route;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssignmentValidationService
{
    public function validate(User $actor, array $data): void
    {
        Validator::make($data, [
            'starts_at' => 'required|date|before_or_equal:now',
            'is_primary' => 'required|boolean',
        ])->validate();
        $scope = app(UserAccessScopeService::class);
        // Consistent driver lock serializes competing primary assignment requests.
        $driver = Driver::whereKey($data['driver_id'])->lockForUpdate()->firstOrFail();
        $vehicle = Vehicle::whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();
        $operator = Operator::whereKey($data['operator_id'])->lockForUpdate()->firstOrFail();
        $park = Park::whereKey($data['park_id'])->lockForUpdate()->firstOrFail();
        abort_unless($scope->canAccessPark($actor, $park, AccessLevel::Manage) && $scope->canAccessOperator($actor, $operator, AccessLevel::Manage), 403);
        foreach ([$driver, $vehicle] as $participant) {
            abort_unless($scope->scopeParticipants($participant->newQuery(), $actor, AccessLevel::Manage)->whereKey($participant->id)->exists(), 403);
        }
        foreach (['driver' => $driver, 'vehicle' => $vehicle, 'operator' => $operator, 'park' => $park] as $key => $record) {
            if ($record->status->value !== ($key === 'operator' ? 'approved' : 'active')) {
                throw ValidationException::withMessages([$key.'_id' => 'Select an active / approved record.']);
            }
        }
        if (! $operator->parks()->where('parks.id', $park->id)->wherePivot('status', 'active')->exists()) {
            throw ValidationException::withMessages(['operator_id' => 'The operator is not approved for this park.']);
        }
        if (! empty($data['route_id'])) {
            $route = Route::whereKey($data['route_id'])->lockForUpdate()->firstOrFail();
            if ($route->status->value !== 'active' || ! $park->routes()->where('routes.id', $route->id)->wherePivot('status', 'active')->exists() || ! $operator->routes()->where('routes.id', $route->id)->wherePivot('park_id', $park->id)->wherePivot('status', 'active')->exists()) {
                throw ValidationException::withMessages(['route_id' => 'Select a route approved for both this park and operator.']);
            }
        }
        if (($data['is_primary'] ?? true) && DriverAssignment::where('driver_id', $driver->id)->where('status', 'active')->where('is_primary', true)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->exists()) {
            throw ValidationException::withMessages(['driver_id' => 'End the existing primary assignment before creating another.']);
        }
    }
}
