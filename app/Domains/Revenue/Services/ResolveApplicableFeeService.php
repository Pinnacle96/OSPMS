<?php

namespace App\Domains\Revenue\Services;

use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ResolveApplicableFeeService
{
    public function resolve(RevenueHead $head, string $vehicleType, Park $park, ?int $routeId = null, ?CarbonImmutable $at = null, bool $lock = false): FeeConfiguration
    {
        $at ??= CarbonImmutable::now();
        $headQuery = RevenueHead::whereKey($head->id);
        $head = ($lock ? $headQuery->lockForUpdate() : $headQuery)->first();
        if (! $head || $head->status->value !== 'active') {
            throw ValidationException::withMessages(['revenue_head_id' => 'The revenue head is inactive.']);
        }
        $candidates = FeeConfiguration::where('revenue_head_id', $head->id)->where('status', 'active')->where('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at));
        foreach (['vehicle_type' => $vehicleType, 'lga_id' => $park->lga_id, 'park_id' => $park->id, 'route_id' => $routeId] as $column => $value) {
            $candidates->where(fn ($q) => $q->whereNull($column)->orWhere($column, $value));
        }
        // Locking reads see current committed terms even under MySQL REPEATABLE READ.
        if ($lock) {
            $candidates->lockForUpdate();
        }
        $ranked = $candidates->get()->sort(function ($a, $b) {
            return $this->specificity($b) <=> $this->specificity($a) ?: $b->priority <=> $a->priority ?: $b->effective_from <=> $a->effective_from;
        })->values();
        if ($ranked->isEmpty()) {
            throw ValidationException::withMessages(['fee' => 'No active fee applies to this operating context.']);
        }
        $best = $ranked[0];
        if (isset($ranked[1]) && $this->specificity($best) === $this->specificity($ranked[1]) && $best->priority === $ranked[1]->priority && $best->effective_from->eq($ranked[1]->effective_from)) {
            throw ValidationException::withMessages(['fee' => 'Conflicting fees have equal precedence. Finance must resolve the configuration.']);
        }

        return $best;
    }

    private function specificity(FeeConfiguration $fee): int
    {
        // Geography first, then route, then vehicle category. Priority breaks equally specific matches only.
        return ($fee->park_id ? 8 : ($fee->lga_id ? 4 : 0)) + ($fee->route_id ? 2 : 0) + ($fee->vehicle_type ? 1 : 0);
    }
}
