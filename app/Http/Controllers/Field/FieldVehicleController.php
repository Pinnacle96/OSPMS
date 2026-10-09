<?php

namespace App\Http\Controllers\Field;

use App\Domains\Vehicles\Models\Vehicle;
use Illuminate\Http\Request;

class FieldVehicleController extends FieldLookupController
{
    protected function kind(): string
    {
        return 'vehicles';
    }

    public function show(Request $request, Vehicle $vehicle)
    {
        return $this->detail($request, $vehicle);
    }
}
