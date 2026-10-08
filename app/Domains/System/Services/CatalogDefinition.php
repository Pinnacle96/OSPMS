<?php

namespace App\Domains\System\Services;

use App\Domains\Drivers\Actions\SaveDriverAction;
use App\Domains\Drivers\Models\Driver;
use App\Domains\Operators\Actions\SaveOperatorAction;
use App\Domains\Operators\Models\Operator;
use App\Domains\Revenue\Actions\SaveFeeConfigurationAction;
use App\Domains\Revenue\Actions\SaveRevenueHeadAction;
use App\Domains\Revenue\Models\FeeConfiguration;
use App\Domains\Revenue\Models\RevenueHead;
use App\Domains\Vehicles\Actions\SaveVehicleAction;
use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CatalogDefinition
{
    public const MODELS = ['operators' => Operator::class, 'drivers' => Driver::class, 'vehicles' => Vehicle::class, 'revenue-heads' => RevenueHead::class, 'fee-configurations' => FeeConfiguration::class];

    public const PAGES = ['operators' => 'Operators', 'drivers' => 'Drivers', 'vehicles' => 'Vehicles', 'revenue-heads' => 'RevenueHeads', 'fee-configurations' => 'Fees'];

    public const ACTIONS = ['operators' => SaveOperatorAction::class, 'drivers' => SaveDriverAction::class, 'vehicles' => SaveVehicleAction::class, 'revenue-heads' => SaveRevenueHeadAction::class, 'fee-configurations' => SaveFeeConfigurationAction::class];

    public function kind(Request $request): string
    {
        return $request->route('catalog');
    }

    public function model(string $kind): string
    {
        return self::MODELS[$kind] ?? abort(404);
    }

    public function record(string $kind, string $id): Model
    {
        return $this->model($kind)::where('public_id', $id)->firstOrFail();
    }
}
