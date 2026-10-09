<?php

namespace App\Domains\Enforcement\Actions;

use App\Domains\Enforcement\Models\Inspection;
use App\Domains\Enforcement\Services\InspectionContextService;
use App\Domains\Identity\Models\User;
use App\Http\Requests\Enforcement\RecordInspectionRequest;
use App\Support\ConfirmedActionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordInspectionAction
{
    public function execute(User $actor, array $input): Inspection
    {
        Gate::forUser($actor)->authorize('create', Inspection::class);
        $data = Validator::make($input, RecordInspectionRequest::inspectionRules())->validate();
        $data['notes'] = trim($data['notes'] ?? '');
        if ($data['result'] !== 'compliant' && mb_strlen($data['notes']) < 10) {
            throw ValidationException::withMessages(['notes' => 'Provide at least 10 characters of observations for this result.']);
        }

        app(InspectionContextService::class)->authorizeContext($actor, $data);

        return app(ConfirmedActionService::class)->execute($actor, $data['idempotency_key'], 'inspection.record', array_diff_key($data, ['idempotency_key' => true]), Inspection::class, function () use ($actor, $data) {
            Gate::forUser($actor)->authorize('create', Inspection::class);
            $service = app(InspectionContextService::class);
            $context = $service->resolve($actor, $data, true);
            if (($data['inspection_type'] === 'ticket_verification') !== ($context['ticket_id'] !== null)) {
                throw ValidationException::withMessages(['inspection_type' => 'Select ticket verification only when inspecting a verified ticket.']);
            }
            if ($data['result'] === 'compliant' && ! $service->canPass($context, true)) {
                throw ValidationException::withMessages(['result' => 'Recorded status, expiry or ticket checks need attention. Record non-compliant or requires review with observations.']);
            }
            $r = Inspection::create(array_intersect_key($context, array_flip(['ticket_id', 'park_id', 'operator_id', 'driver_id', 'vehicle_id'])) + ['inspection_reference' => 'OSPM-INSP-'.Str::ulid(), 'officer_user_id' => $actor->id, 'inspection_type' => $data['inspection_type'], 'result' => $data['result'], 'notes' => $data['notes'] ?: null, 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null, 'occurred_at' => now(), 'created_at' => now()]);
            activity('enforcement')->causedBy($actor)->performedOn($r)->withProperties(['reference' => $r->inspection_reference, 'type' => $r->inspection_type, 'result' => $r->result->value, 'notes' => $r->notes])->log('inspection_recorded');

            return $r;
        });
    }
}
