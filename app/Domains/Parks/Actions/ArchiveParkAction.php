<?php

namespace App\Domains\Parks\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Parks\Models\Park;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ArchiveParkAction
{
    public function execute(User $actor, Park $record): void
    {
        DB::transaction(function () use ($actor, $record) {
            $record = Park::whereKey($record->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $record);
            if (DB::table('tickets')->where('park_id', $record->id)->exists() || $record->routes()->withTrashed()->exists() || DB::table('operator_park')->where('park_id', $record->id)->exists() || DB::table('driver_assignments')->where('park_id', $record->id)->exists() || DB::table('fee_configurations')->where('park_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['archive' => 'This record has linked history. Use its inactive status to preserve the registry history.']);
            }
            $record->delete();
            activity('parks')->causedBy($actor)->performedOn($record)->log('park_archived');
        });
    }
}
