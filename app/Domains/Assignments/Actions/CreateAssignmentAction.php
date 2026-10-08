<?php

namespace App\Domains\Assignments\Actions;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Assignments\Services\AssignmentValidationService;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateAssignmentAction
{
    public function execute(User $actor, array $data): DriverAssignment
    {
        Gate::forUser($actor)->authorize('create', DriverAssignment::class);

        return DB::transaction(function () use ($actor, $data) {
            app(AssignmentValidationService::class)->validate($actor, $data);
            $r = DriverAssignment::create(Arr::only($data, ['driver_id', 'vehicle_id', 'operator_id', 'park_id', 'route_id', 'starts_at', 'is_primary']) + ['status' => 'active', 'assigned_by' => $actor->id]);
            activity('assignments')->causedBy($actor)->performedOn($r)->log('assignment_created');

            return $r;
        });
    }
}
