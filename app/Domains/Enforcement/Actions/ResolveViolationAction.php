<?php

namespace App\Domains\Enforcement\Actions;

use App\Domains\Enforcement\Models\Violation;
use App\Domains\Identity\Models\User;
use App\Http\Requests\Enforcement\ResolveViolationRequest;
use App\Support\ConfirmedActionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResolveViolationAction
{
    public function execute(User $u, Violation $r, array $input): Violation
    {
        Gate::forUser($u)->authorize('resolve', $r);
        $data = validator($input, ResolveViolationRequest::inputRules())->validate();
        $data['resolution'] = trim($data['resolution']);
        if (mb_strlen($data['resolution']) < 10) {
            throw ValidationException::withMessages(['resolution' => 'Explain the resolution in at least ten characters.']);
        }

        return app(ConfirmedActionService::class)->execute($u, $data['idempotency_key'], 'resolve_violation', ['violation' => $r->public_id, ...array_diff_key($data, ['idempotency_key' => true])], Violation::class, function () use ($u, $r, $data) {
            $r = Violation::whereKey($r->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($u)->authorize('resolve', $r);
            if ($r->status->value !== 'open') {
                throw ValidationException::withMessages(['resolution' => 'This violation has already been resolved. Refresh to review its history.']);
            }
            $r->update(['status' => 'resolved', 'resolution' => $data['resolution'], 'resolved_by' => $u->id, 'resolved_at' => now()]);
            activity('enforcement')->causedBy($u)->performedOn($r)->withProperties(['from' => 'open', 'to' => 'resolved', 'reason' => $data['resolution']])->log('violation_resolved');

            return $r;
        });
    }
}
