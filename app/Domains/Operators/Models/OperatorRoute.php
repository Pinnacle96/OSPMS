<?php

namespace App\Domains\Operators\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OperatorRoute extends Pivot
{
    protected $table = 'operator_route';

    public $incrementing = true;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function getUpdatedAtColumn()
    {
        return null;
    }
}
