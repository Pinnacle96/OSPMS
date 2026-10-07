<?php

namespace App\Domains\Routes\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Routes\Models\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ArchiveRouteAction
{
    public function execute(User $actor, Route $record): void
    {
        DB::transaction(function () use ($actor, $record) {
            $record = Route::whereKey($record->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $record);
            if ($record->parks()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['archive' => 'This record has linked parks. Use its inactive status to preserve the registry history.']);
            }
            $record->delete();
            activity('routes')->causedBy($actor)->performedOn($record)->log('route_archived');
        });
    }
}
