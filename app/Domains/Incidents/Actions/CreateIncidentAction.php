<?php

namespace App\Domains\Incidents\Actions;

use App\Domains\Enforcement\Services\InspectionContextService;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Incidents\Models\Incident;
use App\Domains\Incidents\Services\EvidenceService;
use App\Domains\Parks\Models\Park;
use App\Http\Requests\Incidents\CreateIncidentRequest;
use App\Support\ConfirmedActionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateIncidentAction
{
    public function execute(User $u, array $input): Incident
    {
        Gate::forUser($u)->authorize('create', Incident::class);
        $data = validator($input, CreateIncidentRequest::inputRules())->validate();
        $data['description'] = trim($data['description']);
        if (mb_strlen($data['description']) < 10) {
            throw ValidationException::withMessages(['description' => 'Describe the observation in at least ten characters.']);
        }
        if ($data['file'] ?? null) {
            abort_unless($u->can('upload_evidence'), 403);
        }
        $this->park($u, $data['park']);
        if ($data['context'] ?? null) {
            app(InspectionContextService::class)->authorizeContext($u, $data);
        }
        $e = app(EvidenceService::class);
        $payload = array_diff_key($data, array_flip(['idempotency_key', 'file']));
        $payload['file'] = $e->fingerprint($data['file'] ?? null);

        return $e->stored($data['file'] ?? null, function ($path, $meta) use ($u, $data, $payload, $e) {
            return app(ConfirmedActionService::class)->execute($u, $data['idempotency_key'], 'create_incident', $payload, Incident::class, function () use ($u, $data, $path, $meta, $e) {
                Gate::forUser($u)->authorize('create', Incident::class);
                $p = $this->park($u, $data['park'], true);
                $context = ($data['context'] ?? null) ? app(InspectionContextService::class)->resolve($u, $data, true) : [];
                if ($context && $context['park_id'] !== $p->id) {
                    throw ValidationException::withMessages(['context' => 'Choose an assignment in the selected park.']);
                }
                $r = Incident::create(['incident_reference' => 'INC-'.Str::ulid(), 'category' => $data['category'], 'park_id' => $p->id, 'reporter_user_id' => $u->id, ...array_intersect_key($context, array_flip(['operator_id', 'driver_id', 'vehicle_id'])), 'description' => $data['description'], 'status' => 'reported', 'occurred_at' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['occurred_at'], 'Africa/Lagos')->setTimezone(config('app.timezone'))->startOfMinute()]);
                activity('incidents')->causedBy($u)->performedOn($r)->withProperties(['status' => 'reported', 'description' => $r->description])->log('incident_reported');
                if ($path) {
                    $e->attach($u, $r, $path, $meta);
                }

                return $r;
            });
        });
    }

    private function park(User $u, string $id, bool $lock = false): Park
    {
        $p = app(UserAccessScopeService::class)->scopeParks(Park::query(), $u)->where('public_id', $id)->where('status', 'active')->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if (! $p) {
            throw ValidationException::withMessages(['park' => 'Choose an active park in your current scope.']);
        }

        return $p;
    }
}
