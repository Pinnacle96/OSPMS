<?php

namespace App\Http\Controllers\Field;

use App\Domains\Operators\Models\Operator;
use Illuminate\Http\Request;

class FieldOperatorController extends FieldLookupController
{
    protected function kind(): string
    {
        return 'operators';
    }

    public function show(Request $request, Operator $operator)
    {
        return $this->detail($request, $operator);
    }
}
