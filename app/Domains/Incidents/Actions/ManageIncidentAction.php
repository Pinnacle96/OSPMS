<?php

namespace App\Domains\Incidents\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Models\Incident;
use App\Http\Requests\Incidents\ManageIncidentRequest;
use App\Support\ConfirmedActionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageIncidentAction
{
    public const TRANSITIONS = ['reported' => ['under_review', 'escalated'], 'under_review' => ['escalated', 'resolved'], 'escalated' => ['under_review', 'resolved'], 'resolved' => ['closed'], 'closed' => []];

    public function execute(User $u, Incident $r, array $input): Incident
    {
        Gate::forUser($u)->authorize('manage', $r);
        $data = validator($input, ManageIncidentRequest::inputRules())->validate();
        $data['resolution'] = trim($data['resolution']);
        if (mb_strlen($data['resolution']) < 10) {
            throw ValidationException::withMessages(['resolution' => 'Explain this status change in at least ten characters.']);
        }

        return app(ConfirmedActionService::class)->execute($u, $data['idempotency_key'], 'manage_incident', ['incident' => $r->public_id, ...array_diff_key($data, ['idempotency_key' => true])], Incident::class, function () use ($u, $r, $data) {
            $r = Incident::whereKey($r->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($u)->authorize('manage', $r);
            $old = $r->status->value;
            if ($old !== $data['expected_status'] || ! in_array($data['status'], self::TRANSITIONS[$old], true)) {
                throw ValidationException::withMessages(['status' => 'The incident changed or this transition is unavailable. Refresh and review the current status.']);
            }
            $changes = ['status' => $data['status']];
            if ($data['status'] === 'resolved') {
                $changes += ['resolution' => $data['resolution'], 'resolved_by' => $u->id, 'resolved_at' => now()];
            }
            $r->update($changes);
            activity('incidents')->causedBy($u)->performedOn($r)->withProperties(['from' => $old, 'to' => $data['status'], 'reason' => $data['resolution']])->log('incident_status_changed');

            return $r;
        });
    }
}
