<?php

namespace App\Domains\Assignments\Actions;

use App\Domains\Assignments\Models\DriverAssignment;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EndAssignmentAction
{
    public function execute(User $actor, DriverAssignment $assignment, string $endsAt): DriverAssignment
    {
        return DB::transaction(function () use ($actor, $assignment, $endsAt) {
            Driver::whereKey($assignment->driver_id)->lockForUpdate()->firstOrFail();
            $r = DriverAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('end', $r);
            $date = CarbonImmutable::parse($endsAt);
            if ($r->status->value === 'ended' || $date->lt($r->starts_at) || $date->isFuture()) {
                throw ValidationException::withMessages(['ends_at' => 'Use an end time between the assignment start and now.']);
            }
            $r->update(['ends_at' => $date, 'status' => 'ended']);
            activity('assignments')->causedBy($actor)->performedOn($r)->log('assignment_ended');

            return $r;
        });
    }
}
