<?php

namespace App\Domains\Parks\Actions;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Enums\AccessLevel;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveParkAction
{
    public function execute(User $actor, array $data, ?Park $park = null): Park
    {
        return DB::transaction(function () use ($actor, $data, $park) {
            $record = $park ? Park::whereKey($park->id)->lockForUpdate()->firstOrFail() : new Park;
            Gate::forUser($actor)->authorize($park ? 'update' : 'create', $park ? $record : Park::class);
            $lga = Lga::whereKey($data['lga_id'])->lockForUpdate()->firstOrFail();
            $scopes = app(UserAccessScopeService::class);
            if (! $scopes->isStatewide($actor) && (int) $data['lga_id'] !== (int) $record->lga_id) {
                abort_unless($scopes->canAccessLga($actor, $lga, AccessLevel::Manage), 403);
            }
            if ($data['status'] === 'active' && $lga->status->value !== 'active') {
                throw ValidationException::withMessages(['status' => 'An active park must belong to an active LGA.']);
            }
            $before = $record->getAttributes();
            $record->fill(Arr::only($data, ['park_code', 'lga_id', 'name', 'address', 'latitude', 'longitude', 'category', 'contact_phone', 'status']));
            if (! $park) {
                $record->created_by = $actor->id;
            }
            $record->updated_by = $actor->id;
            if ($data['status'] === 'active' && ! $record->activated_at) {
                $record->activated_at = now();
            }
            $changes = $record->getDirty();
            $record->save();
            activity('parks')->causedBy($actor)->performedOn($record)->withProperties(['before' => Arr::only($before, array_keys($changes)), 'after' => $changes])->log($park ? 'park_updated' : 'park_created');
            if ($park && isset($changes['status'])) {
                activity('parks')->causedBy($actor)->performedOn($record)->withProperties(['from' => $before['status'], 'to' => $changes['status']])->log('park_status_changed');
            }

            return $record;
        });
    }
}
