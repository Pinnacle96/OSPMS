<?php

namespace App\Domains\Enforcement\Actions;

use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Enforcement\Models\Violation;
use App\Domains\Identity\Models\User;
use App\Http\Requests\Enforcement\RecordViolationRequest;
use App\Support\ConfirmedActionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordViolationAction
{
    public function execute(User $u, Inspection $i, array $input): Violation
    {
        Gate::forUser($u)->authorize('create', Violation::class);
        Gate::forUser($u)->authorize('view', $i);
        $data = validator($input, RecordViolationRequest::inputRules())->validate();
        foreach (['category' => 2, 'description' => 10] as $key => $min) {
            $data[$key] = trim($data[$key]);
            if (mb_strlen($data[$key]) < $min) {
                throw ValidationException::withMessages([$key => 'Please provide a meaningful observation.']);
            }
        }

        return app(ConfirmedActionService::class)->execute($u, $data['idempotency_key'], 'record_violation', ['inspection' => $i->public_id, 'category' => $data['category'], 'description' => $data['description']], Violation::class, function () use ($u, $i, $data) {
            $i = Inspection::whereKey($i->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($u)->authorize('create', Violation::class);
            Gate::forUser($u)->authorize('view', $i);
            if ($i->result->value === 'compliant') {
                throw ValidationException::withMessages(['description' => 'A violation requires a non-compliant or review inspection. Record a new observation if circumstances changed.']);
            }
            $r = Violation::create(['inspection_id' => $i->id, ...$i->only(['park_id', 'operator_id', 'driver_id', 'vehicle_id']), 'violation_reference' => 'VIO-'.Str::ulid(), 'category' => $data['category'], 'description' => $data['description'], 'status' => 'open', 'issued_by' => $u->id, 'issued_at' => now()]);
            activity('enforcement')->causedBy($u)->performedOn($r)->withProperties(['status' => 'open', 'description' => $r->description, 'inspection' => $i->public_id])->log('violation_recorded');

            return $r;
        });
    }
}
