<?php

namespace App\Domains\Geography\Actions;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveLgaAction
{
    public function execute(User $actor, array $data, ?Lga $lga = null): Lga
    {
        return DB::transaction(function () use ($actor, $data, $lga) {
            $record = $lga ? Lga::whereKey($lga->id)->lockForUpdate()->firstOrFail() : new Lga;
            Gate::forUser($actor)->authorize($lga ? 'update' : 'create', $lga ? $record : Lga::class);
            if ($data['status'] === 'inactive' && $lga && $record->parks()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['status' => 'Deactivate or suspend active parks before deactivating this LGA.']);
            }
            $before = $record->getAttributes();
            $record->fill(Arr::only($data, ['code', 'name', 'administrative_contact_name', 'administrative_contact_phone', 'administrative_contact_email', 'status']));
            $changes = $record->getDirty();
            $record->save();
            activity('geography')->causedBy($actor)->performedOn($record)->withProperties(['before' => Arr::only($before, array_keys($changes)), 'after' => $changes])->log($lga ? 'lga_updated' : 'lga_created');
            if ($lga && isset($changes['status'])) {
                activity('geography')->causedBy($actor)->performedOn($record)->withProperties(['from' => $before['status'], 'to' => $changes['status']])->log('lga_status_changed');
            }

            return $record;
        });
    }
}
