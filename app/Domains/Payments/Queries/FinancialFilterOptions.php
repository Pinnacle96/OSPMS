<?php

namespace App\Domains\Payments\Queries;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Parks\Models\Park;
use App\Domains\Revenue\Models\RevenueHead;

class FinancialFilterOptions
{
    public function get(User $user): array
    {
        $scope = app(UserAccessScopeService::class);

        return ['parks' => $scope->scopeParks(Park::query(), $user)->orderBy('name')->get(['id', 'name']), 'lgas' => $scope->scopeLgas(Lga::query(), $user)->orderBy('name')->get(['id', 'name']), 'revenue_heads' => RevenueHead::orderBy('name')->get(['id', 'name'])];
    }
}
