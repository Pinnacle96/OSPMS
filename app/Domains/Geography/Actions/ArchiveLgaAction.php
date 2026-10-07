<?php

namespace App\Domains\Geography\Actions;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ArchiveLgaAction
{
    public function execute(User $actor, Lga $record): void
    {
        DB::transaction(function () use ($actor, $record) {
            $record = Lga::whereKey($record->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $record);
            if ($record->parks()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['archive' => 'This record has linked parks. Use its inactive status to preserve the registry history.']);
            }
            $record->delete();
            activity('geography')->causedBy($actor)->performedOn($record)->log('lga_archived');
        });
    }
}
