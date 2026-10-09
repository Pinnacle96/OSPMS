<?php

namespace App\Domains\Complaints\Actions;

use App\Domains\Complaints\Models\Complaint;
use App\Domains\Enforcement\Services\InspectionContextService;
use App\Domains\Identity\Models\User;
use App\Domains\Incidents\Services\EvidenceService;
use App\Domains\Incidents\Services\OperationalScope;
use App\Domains\Parks\Models\Park;
use App\Http\Requests\Complaints\CreateComplaintRequest;
use App\Support\ConfirmedActionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateComplaintAction
{
    public function execute(?User $u, array $input, ?string $publicSession = null): Complaint
    {
        if (! $u && ! $publicSession) {
            throw new \LogicException('Public submissions require a session binding.');
        }
        if ($u) {
            Gate::forUser($u)->authorize('create', Complaint::class);
        }
        $rules = CreateComplaintRequest::inputRules();
        if (! $u) {
            $rules['context'] = 'prohibited';
        }
        foreach (['complainant_name', 'complainant_phone', 'complainant_email', 'category', 'description'] as $k) {
            if (is_string($input[$k] ?? null)) {
                $input[$k] = trim($input[$k]);
            }
        }
        $d = validator($input, $rules)->validate();
        $this->park($u, $d['park'] ?? null);
        if ($u && ($d['context'] ?? null)) {
            app(InspectionContextService::class)->authorizeContext($u, $d);
        }
        if ($u && ($d['file'] ?? null)) {
            abort_unless($u->can('upload_evidence'), 403);
        }
        $e = app(EvidenceService::class);
        $payload = array_diff_key($d, array_flip(['idempotency_key', 'file']));
        $payload['file'] = $e->fingerprint($d['file'] ?? null);
        $payload['session'] = $publicSession;

        return $e->stored($d['file'] ?? null, fn ($path, $meta) => app(ConfirmedActionService::class)->execute($u, $d['idempotency_key'], 'create_complaint', $payload, Complaint::class, function () use ($u, $d, $path, $meta, $e) {
            if ($u) {
                Gate::forUser($u)->authorize('create', Complaint::class);
            }
            $p = $this->park($u, $d['park'] ?? null, true);
            $ctx = $u && ($d['context'] ?? null) ? app(InspectionContextService::class)->resolve($u, $d, true) : [];
            if ($ctx && $ctx['park_id'] !== $p?->id) {
                throw ValidationException::withMessages(['context' => 'Choose a current assignment in this park.']);
            }
            $c = Complaint::create([...array_intersect_key($d, array_flip(['complainant_name', 'complainant_phone', 'complainant_email', 'category', 'description'])), 'complaint_reference' => 'CMP-'.Str::ulid(), 'park_id' => $p?->id, ...array_intersect_key($ctx, array_flip(['operator_id', 'driver_id', 'vehicle_id'])), 'submitted_by' => $u?->id, 'source' => $u ? ($u->hasRole('Help Desk Officer') ? 'help_desk' : 'staff') : 'public_web', 'status' => 'submitted']);
            activity('complaints')->causedBy($u)->performedOn($c)->withProperties(['status' => 'submitted', 'source' => $c->source->value])->log('complaint_submitted');
            if ($path) {
                $e->attach($u, $c, $path, $meta);
            }

return $c;
        }));
    }

    private function park(?User $u, ?string $id, bool $lock = false): ?Park
    {
        if (! $id) {
            return null;
        }
        $q = $u ? app(OperationalScope::class)->parks($u)->whereNull('deleted_at') : Park::query();
        $p = $q->where('public_id', $id)->where('status', 'active')->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if (! $p) {
            throw ValidationException::withMessages(['park' => 'Choose an active available park.']);
        }

return $p;
    }
}
