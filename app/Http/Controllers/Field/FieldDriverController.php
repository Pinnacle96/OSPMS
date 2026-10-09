<?php

namespace App\Http\Controllers\Field;

use App\Domains\Drivers\Models\Driver;
use Illuminate\Http\Request;

class FieldDriverController extends FieldLookupController
{
    protected function kind(): string
    {
        return 'drivers';
    }

    public function show(Request $request, Driver $driver)
    {
        return $this->detail($request, $driver);
    }
}
