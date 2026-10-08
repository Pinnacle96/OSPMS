<?php

namespace App\Domains\Revenue\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Revenue\Models\RevenueHead;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveRevenueHeadAction
{
    public function execute(User $actor, array $data, ?RevenueHead $head = null): RevenueHead
    {
        return DB::transaction(function () use ($actor, $data, $head) {
            $r = $head ? RevenueHead::whereKey($head->id)->lockForUpdate()->firstOrFail() : new RevenueHead;
            Gate::forUser($actor)->authorize($head ? 'update' : 'create', $head ? $r : RevenueHead::class);
            $r->fill(Arr::only($data, ['code', 'name', 'description', 'frequency', 'status']));
            if (! $head) {
                $r->created_by = $actor->id;
            } $r->save();
            activity('revenue')->causedBy($actor)->performedOn($r)->log($head ? 'revenue_head_updated' : 'revenue_head_created');

            return $r;
        });
    }
}
