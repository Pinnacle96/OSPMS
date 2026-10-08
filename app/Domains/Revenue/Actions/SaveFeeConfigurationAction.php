<?php

namespace App\Domains\Revenue\Actions;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Routes\Models\Route;
use App\Support\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveFeeConfigurationAction
{
    public function execute(User $actor, array $data, ?FeeConfiguration $fee = null): FeeConfiguration
    {
        return DB::transaction(function () use ($actor, $data, $fee) {
            $r = $fee ? FeeConfiguration::whereKey($fee->id)->lockForUpdate()->firstOrFail() : new FeeConfiguration;
            Gate::forUser($actor)->authorize($fee ? 'update' : 'create', $fee ? $r : FeeConfiguration::class);
            $fields = ['revenue_head_id', 'amount', 'currency', 'vehicle_type', 'lga_id', 'park_id', 'route_id', 'priority', 'effective_from', 'effective_to', 'status'];
            $wasEffective = $r->exists && $r->effective_from->lte(now());
            $previousStatus = $r->exists ? $r->status->value : null;
            $data = Arr::only($data, $fields);
            $data['amount'] = Money::normalize((string) $data['amount']);
            $r->fill($data);
            if ($wasEffective) {
                $changed = array_diff(array_keys($r->getDirty()), ['status']);
                if ($changed || ! in_array($data['status'], [$previousStatus, 'inactive'], true)) {
                    throw ValidationException::withMessages(['amount' => 'Effective fee terms are immutable. Create a new future configuration; this record may only be deactivated.']);
                }
            }
            $head = RevenueHead::whereKey($data['revenue_head_id'])->lockForUpdate()->firstOrFail();
            if ($data['status'] === 'active' && $head->status->value !== 'active') {
                throw ValidationException::withMessages(['revenue_head_id' => 'Activate the revenue head before activating this fee.']);
            }
            if (! empty($data['lga_id'])) {
                Lga::findOrFail($data['lga_id']);
            }
            if (! empty($data['park_id'])) {
                $park = Park::findOrFail($data['park_id']);
                if (! empty($data['lga_id']) && $park->lga_id != (int) $data['lga_id']) {
                    throw ValidationException::withMessages(['lga_id' => 'The park must belong to the selected LGA.']);
                }
            }
            if (! empty($data['route_id'])) {
                Route::findOrFail($data['route_id']);
                if (isset($park) && ! $park->routes()->where('routes.id', $data['route_id'])->wherePivot('status', 'active')->exists()) {
                    throw ValidationException::withMessages(['route_id' => 'The route must be approved for the selected park.']);
                }
            }
            if (! $fee) {
                $r->created_by = $actor->id;
            }
            if ($data['status'] === 'active' && $previousStatus !== 'active') {
                $r->approved_by = $actor->id;
            }
            $r->save();
            activity('revenue')->causedBy($actor)->performedOn($r)->withProperties(['terms' => $r->only($fields)])->log($fee ? 'fee_configuration_changed' : 'fee_configuration_created');

            return $r;
        }, 3);
    }
}
